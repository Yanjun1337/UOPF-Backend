<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Relationship\User as UserRelationshipManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\Entry as EmbeddableEntry;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\OrderValidator;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * Users Followings
 */
final class UsersFollowings extends Endpoint {
    public function read(Response $response): EmbeddableList {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$user = UserManager::fetchEntry($id))
            $this->throwNotFoundException();

        $filtered = $this->filterBody(new DictionaryValidator([
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
            )
        ]));

        $retrieved = UserRelationshipManager::queryEntries([
            'AND' => [
                'type' => UserRelationshipManager::getProperty('type'),
                'subject' => $user['id']
            ],

            'LIMIT' => Database::getPagingLimit($filtered['perPage'], $filtered['page']),
            'ORDER' => ['created' => $filtered['order']],
            'TOTAL' => true
        ]);

        $results = [];

        foreach ($retrieved->entries as $relationship) {
            $results[] = [
                'relationship' => $relationship,
                'user' => new EmbeddableEntry($relationship['object'], 'user')
            ];
        }

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($results);
    }
}
