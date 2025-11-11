<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Topic as TopicManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;
use UOPF\Validator\Extension\SearchKeywordsValidator;

/**
 * Topics
 */
final class Topics extends Endpoint {
    public function read(Response $response): EmbeddableList {
        if (!$this->isAdministrative())
            $this->throwPermissionDeniedException();

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
                    'modified',
                    'records'
                ])
            ),

            'search' => new DictionaryValidatorElement(
                label: 'Search Keywords',
                validator: new SearchKeywordsValidator()
            )
        ]));

        if ($filtered['orderby'] === 'records')
            $orderby = "_{$filtered['orderby']}";
        else
            $orderby = $filtered['orderby'];

        $where = [
            'TOTAL' => true,
            'ORDER' => [$orderby => $filtered['order']],
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page'])
        ];

        if (isset($filtered['search'])) {
            $where['OR # search'] = Database::getSearchClause(
                $filtered['search'],
                ['title']
            );
        }

        $retrieved = TopicManager::queryEntries($where);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($retrieved->entries);
    }
}
