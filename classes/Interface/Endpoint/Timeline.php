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
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * Timeline
 */
final class Timeline extends Endpoint {
    public function read(Response $response): EmbeddableList {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterQuery(new DictionaryValidator([
            'before' => new DictionaryValidatorElement(
                label: 'Immediately Previous Post',
                validator: new IdValidator()
            ),

            'perPage' => new DictionaryValidatorElement(
                label: 'Number per Page',
                default: 10,
                validator: new NumberPerPageValidator()
            )
        ]));

        $parameters = [
            ':subject' => $current['id'],
            ':_user' => 'u',
            ':_post' => 'post',
            ':_publish' => 'publish'
        ];

        if (isset($filtered['before'])) {
            $parameters[':before'] = $filtered['before'];

            $relationshipsWhereClause = '`records`.`id` < :before AND';
            $recordsWhereClause = '`id` < :before AND';
        } else {
            $relationshipsWhereClause = '';
            $recordsWhereClause = '';
        }

        $perPage = $filtered['perPage'] + 1;

        $sql = trim("
    SELECT `records`.*
    FROM `records` as `records`
    INNER JOIN `relationships` as `relationships` ON
        `relationships`.`type` = :_user AND
        `relationships`.`subject` = :subject AND
        `relationships`.`object` = `records`.`user`
    WHERE
        {$relationshipsWhereClause}
        `records`.`type` = :_post AND
        `records`.`affiliated_to` IS NULL AND
        `records`.`status` = :_publish
UNION ALL
    SELECT * FROM `records` WHERE
        {$recordsWhereClause}
        `type` = :_post AND
        `affiliated_to` IS NULL AND
        `status` = :_publish AND
        `user` = :subject
ORDER BY `created` DESC LIMIT {$perPage}"
        );

        $retrieved = RecordManager::queryEntriesArbitrarily($sql, $parameters);
        $entries = $retrieved->entries;

        if (count($entries) > $filtered['perPage']) {
            array_pop($entries);
            $hasMore = true;
        } else {
            $hasMore = false;
        }

        $response->headers->set('X-API-More', $hasMore ? 'true' : 'false');
        return new EmbeddableList($entries);
    }
}
