<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * Repost Notifications
 */
final class NotificationsReposts extends Endpoint {
    public function read(Response $response): EmbeddableList {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

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
            )
        ]));

        $limit = Database::getPagingLimit($filtered['perPage'], $filtered['page']);

        if (is_array($limit))
            $limitClause = sprintf('%1$s, %2$s', $limit[0], $limit[1]);
        else
            $limitClause = strval($limit);

        $sql = trim("
SELECT SQL_CALC_FOUND_ROWS `reposts`.*
FROM `records` as `reposts`
INNER JOIN `records` as `parents` ON
    `parents`.`type` = 'post' AND
    `parents`.`status` = 'publish' AND
    `parents`.`affiliated_to` IS NULL AND
    `parents`.`user` = :user
WHERE
    `reposts`.`type` = 'post' AND
    `reposts`.`status` = 'publish' AND
    `reposts`.`affiliated_to` IS NULL AND
    `reposts`.`parent` = `parents`.`id` AND
    `reposts`.`user` != :user
ORDER BY `created` DESC LIMIT {$limitClause}
        ");

        $parameters = [':user' => $current['id']];
        $retrieved = RecordManager::queryEntriesArbitrarily($sql, $parameters);

        static::setPagingOnResponse($response, $retrieved->total, $filtered['perPage']);
        return new EmbeddableList($retrieved->entries);
    }
}
