<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\Record;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\IdListValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Comment Children
 */
final class CommentsChildren extends Endpoint {
    public function read(Response $response): EmbeddableList {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$comment = RecordManager::fetchEntry($id))
            $this->throwNotFoundException();

        if ($comment['type'] !== 'comment' || $comment['status'] !== 'publish')
            $this->throwNotFoundException();

        if (isset($comment['parent']))
            $this->throwNotFoundException();

        $filtered = $this->filterQuery(new DictionaryValidator([
            'page' => new DictionaryValidatorElement(
                label: 'Page',
                default: 1,
                validator: new PageNumberValidator()
            ),

            'perPage' => new DictionaryValidatorElement(
                label: 'Number per Page',
                default: 5,
                validator: new NumberPerPageValidator(20)
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
                    'replies'
                ])
            ),

            'exclude' => new DictionaryValidatorElement(
                label: 'Excluding IDs',

                validator: new IdListValidator(
                    allowEmpty: false,
                    maximum: 100
                )
            )
        ]));

        if ($filtered['orderby'] === 'created')
            $orderby = $filtered['orderby'];
        elseif ($filtered['orderby'] === 'replies')
            $orderby = '_reposts';
        else
            $orderby = "_{$filtered['orderby']}";

        $retrieved = $comment->getChildrenRecursively(
            page: $filtered['page'],
            perPage: $filtered['perPage'],
            order: $filtered['order'],
            orderby: $orderby,
            exclude: $filtered['exclude'] ?? null
        );

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($retrieved->entries);
    }
}
