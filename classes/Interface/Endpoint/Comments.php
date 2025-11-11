<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\Record;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Interface\EntryWith\RecordWithChildren;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\StringValidator;
use UOPF\Validator\BooleanValidator;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\IdListValidator;
use UOPF\Validator\Extension\ZeroableIdValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\RecordContentValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Comments
 */
final class Comments extends Endpoint {
    public function read(Response $response): EmbeddableList {
        $filtered = $this->filterQuery(new DictionaryValidator([
            'post' => new DictionaryValidatorElement(
                label: 'Post ID',
                validator: new IdValidator()
            ),

            'page' => new DictionaryValidatorElement(
                label: 'Page',
                default: 1,
                validator: new PageNumberValidator()
            ),

            'perPage' => new DictionaryValidatorElement(
                label: 'Number per Page',
                default: 10,
                validator: new NumberPerPageValidator()
            ),

            'order' => new DictionaryValidatorElement(
                label: 'Order',
                default: OrderValidator::DESCENDING,
                validator: new OrderValidator()
            ),

            'orderby' => new DictionaryValidatorElement(
                label: 'Orderby',
                default: 'created',

                validator: new EnumerationValidator([
                    'created',
                    'likes',
                    'replies',
                    'modified'
                ])
            ),

            'parent' => new DictionaryValidatorElement(
                label: 'Comment Parent',
                validator: new ZeroableIdValidator()
            ),

            'withChildren' => new DictionaryValidatorElement(
                label: 'Attaching Children',
                default: false,
                validator: new BooleanValidator()
            ),

            'status' => new DictionaryValidatorElement(
                label: 'Comment Status',
                default: 'publish',

                validator: new EnumerationValidator([
                    'publish',
                    'all',
                    'blocked',
                    'trashed'
                ])
            ),

            'search' => new DictionaryValidatorElement(
                label: 'Search Keywords',

                validator: new StringValidator(
                    allowEmpty: false,
                    max: 1024
                )
            ),

            'user' => new DictionaryValidatorElement(
                label: 'Comment Author',
                validator: new IdValidator()
            )
        ]));

        $conditions = [
            'type' => 'comment'
        ];

        if (isset($filtered['post']))
            $conditions['affiliated_to'] = $filtered['post'];
        elseif (!$this->isAdministrative())
            $this->throwPermissionDeniedException();

        if ($filtered['status'] === 'publish') {
            $conditions['status'] = $filtered['status'];
        } else {
            if (!$this->isAdministrative())
                $this->throwPermissionDeniedException();

            if ($filtered['status'] !== 'all')
                $conditions['status'] = $filtered['status'];
        }

        if (isset($filtered['user'])) {
            if ($this->isAdministrative())
                $conditions['user'] = $filtered['user'];
            else
                $this->throwPermissionDeniedException();
        }

        if (isset($filtered['parent']))
            $conditions['parent'] = $filtered['parent'] === 0 ? null : $filtered['parent'];

        if ($filtered['orderby'] === 'created') {
            $orderby = $filtered['orderby'];
        } elseif ($filtered['orderby'] === 'modified') {
            if ($this->isAdministrative())
                $orderby = $filtered['orderby'];
            else
                $this->throwPermissionDeniedException();
        } elseif ($filtered['orderby'] === 'replies') {
            $orderby = '_reposts';
        } else {
            $orderby = "_{$filtered['orderby']}";
        }

        $where = [
            'AND' => $conditions,
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page']),
            'ORDER' => [$orderby => $filtered['order']],
            'TOTAL' => true
        ];

        if (isset($filtered['search'])) {
            if (!$this->isAdministrative())
                $this->throwPermissionDeniedException();

            $where['MATCH'] = [
                'keyword' => $filtered['search'],
                'columns' => ['title', 'content']
            ];
        }

        $retrieved = RecordManager::queryEntries($where);
        $entries = $retrieved->entries;

        if (isset($filtered['withChildren']))
            $entries = RecordWithChildren::createList($entries);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($entries);
    }

    public function write(Response $response): Record {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'post' => new DictionaryValidatorElement(
                label: 'Post ID',
                required: true,
                validator: new IdValidator()
            ),

            'content' => new DictionaryValidatorElement(
                label: 'Comment Content',
                required: true,
                validator: new RecordContentValidator()
            ),

            'parent' => new DictionaryValidatorElement(
                label: 'Comment Parent',
                validator: new IdValidator()
            ),

            'images' => new DictionaryValidatorElement(
                label: 'Comment Images',
                default: [],
                validator: new IdListValidator()
            )
        ]));

        try {
            $post = RecordManager::publish(
                type: 'comment',
                affiliatedTo: $filtered['post'],
                user: $current['id'],
                content: $filtered['content'],
                parent: $filtered['parent'] ?? null,
                userAgent: $this->request->headers->get('User-Agent'),
                images: $filtered['images']
            );
        } catch (RecordUpdateException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        $response->setStatusCode(201);
        return $post;
    }

    public function delete(Response $response): Record {
        $filtered = $this->filterBody(new DictionaryValidator([
            'id' => new DictionaryValidatorElement(
                label: 'Comment ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        return Database::transaction(function () use (&$filtered) {
            if (!$lockedComment = RecordManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('Comment to delete does not exist.', 'id');

            if ($lockedComment['type'] !== 'comment')
                throw new ParameterException('Comment to delete is invalid.', 'id');

            if (!$this->canEdit($lockedComment))
                $this->throwPermissionDeniedException();

            try {
                RecordManager::trashLocked($lockedComment);
            } catch (RecordUpdateException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }

            return $lockedComment;
        });
    }
}
