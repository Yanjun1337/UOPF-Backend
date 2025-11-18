<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * User Unregistrations
 */
final class CasesUnregisters extends Endpoint {
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

            'status' => new DictionaryValidatorElement(
                label: 'Unregistration Status',
                default: 'review',

                validator: new EnumerationValidator([
                    'review',
                    'all',
                    'completed'
                ])
            ),

            'user' => new DictionaryValidatorElement(
                label: 'User',
                validator: new IdValidator()
            )
        ]));

        $conditions = [
            'type' => 'unregistration'
        ];

        if ($filtered['status'] !== 'all')
            $conditions['status'] = $filtered['status'];

        if (isset($filtered['user']))
            $conditions['user'] = $filtered['user'];

        $where = [
            'AND' => $conditions,
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page']),
            'ORDER' => ['created' => $filtered['order']],
            'TOTAL' => true
        ];

        $retrieved = CaseManager::queryEntries($where);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($retrieved->entries);
    }
}
