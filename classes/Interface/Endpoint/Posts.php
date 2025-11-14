<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Utilities;
use UOPF\DatabaseLockType;
use UOPF\Model\Record;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
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
use UOPF\Validator\Extension\RecordTitleValidator;
use UOPF\Validator\Extension\RecordContentValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Posts
 */
final class Posts extends Endpoint {
    public function read(Response $response): EmbeddableList {
        $filtered = $this->filterQuery(new DictionaryValidator([
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
                    'reposts',
                    'comments',
                    'modified'
                ])
            ),

            'parent' => new DictionaryValidatorElement(
                label: 'Post Parent',
                validator: new ZeroableIdValidator()
            ),

            'user' => new DictionaryValidatorElement(
                label: 'Post Author',
                validator: new ZeroableIdValidator()
            ),

            'search' => new DictionaryValidatorElement(
                label: 'Search Keywords',

                validator: new StringValidator(
                    allowEmpty: false,
                    max: 1024
                )
            ),

            'indexed' => new DictionaryValidatorElement(
                label: 'Indexing Entries',
                default: false,
                validator: new BooleanValidator()
            ),

            'specific' => new DictionaryValidatorElement(
                label: 'Posts IDs',

                validator: new IdListValidator(
                    allowEmpty: false,
                    maximum: 100
                )
            ),

            'status' => new DictionaryValidatorElement(
                label: 'Post Status',
                default: 'publish',

                validator: new EnumerationValidator([
                    'publish',
                    'all',
                    'trashed'
                ])
            )
        ]));

        if (isset($filtered['user']) && $filtered === 0)
            unset($filtered['user']);

        $conditions = [
            'type' => 'post',
            'affiliated_to' => null
        ];

        if ($filtered['status'] === 'publish') {
            $conditions['status'] = $filtered['status'];
        } else {
            if (!$this->isAdministrative())
                $this->throwPermissionDeniedException();

            if ($filtered['status'] !== 'all')
                $conditions['status'] = $filtered['status'];
        }

        if (isset($filtered['parent']))
            $conditions['parent'] = $filtered['parent'] === 0 ? null : $filtered['parent'];

        if (isset($filtered['user']))
            $conditions['user'] = $filtered['user'];

        if ($filtered['orderby'] === 'created') {
            $orderby = $filtered['orderby'];
        } elseif ($filtered['orderby'] === 'modified') {
            if ($this->isAdministrative())
                $orderby = $filtered['orderby'];
            else
                $this->throwPermissionDeniedException();
        } else {
            $orderby = "_{$filtered['orderby']}";
        }

        if (isset($filtered['specific']))
            $conditions['id'] = $filtered['specific'];

        $where = [
            'AND' => $conditions,
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page']),
            'ORDER' => [$orderby => $filtered['order']],
            'TOTAL' => true
        ];

        if (isset($filtered['search'])) {
            $where['MATCH'] = [
                'keyword' => $filtered['search'],
                'columns' => ['title', 'content']
            ];
        }

        $retrieved = RecordManager::queryEntries($where);
        $entries = $retrieved->entries;

        foreach ($entries as $entry)
            RecordManager::countEntryViews($entry['id']);

        if ($filtered['indexed'])
            $entries = array_combine(Utilities::arrayColumn($entries, 'id'), $entries);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($entries);
    }

    public function write(Response $response): Record {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'title' => new DictionaryValidatorElement(
                label: 'Post Title',
                validator: new RecordTitleValidator()
            ),

            'content' => new DictionaryValidatorElement(
                label: 'Post Content',
                required: true,
                validator: new RecordContentValidator()
            ),

            'parent' => new DictionaryValidatorElement(
                label: 'Post Parent',
                validator: new IdValidator()
            ),

            'images' => new DictionaryValidatorElement(
                label: 'Post Images',
                default: [],
                validator: new IdListValidator()
            )
        ]));

        try {
            $post = RecordManager::publish(
                type: 'post',
                user: $current['id'],
                title: $filtered['title'] ?? null,
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
                label: 'Post ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        return Database::transaction(function () use (&$filtered) {
            if (!$lockedPost = RecordManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('Post to delete does not exist.', 'id');

            if ($lockedPost['type'] !== 'post')
                throw new ParameterException('Post to delete is invalid.', 'id');

            if (!$this->canEdit($lockedPost))
                $this->throwPermissionDeniedException();

            try {
                RecordManager::trashLocked($lockedPost);
            } catch (RecordUpdateException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }

            return $lockedPost;
        });
    }
}
