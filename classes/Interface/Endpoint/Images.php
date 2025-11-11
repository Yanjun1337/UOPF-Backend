<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * Images
 */
final class Images extends Endpoint {
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

            'user' => new DictionaryValidatorElement(
                label: 'User',
                validator: new IdValidator()
            ),

            'record' => new DictionaryValidatorElement(
                label: 'Record',
                validator: new IdValidator()
            )
        ]));

        $conditions = [
            'status' => 'publish'
        ];

        if (isset($filtered['user']))
            $conditions['user'] = $filtered['user'];

        if (isset($filtered['record']))
            $conditions['record'] = $filtered['record'];

        $where = [
            'TOTAL' => true,
            'ORDER' => ['created' => $filtered['order']],
            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page'])
        ];

        $retrieved = ImageManager::queryEntries($where);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($retrieved->entries);
    }
}
