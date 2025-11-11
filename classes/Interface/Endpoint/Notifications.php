<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use PDO;
use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Facade\Manager\Relationship\Like as LikeRelationshipManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\PageNumberValidator;
use UOPF\Validator\Extension\NumberPerPageValidator;

/**
 * Notifications
 */
final class Notifications extends Endpoint {
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

        Database::transaction(function () use (&$current) {
            if ($locked = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $locked->setMetadata('unreadNotifications', 0);
            else
                $this->throwInconsistentInternalDataException();
        });

        $limit = Database::getPagingLimit($filtered['perPage'], $filtered['page']);

        if (is_array($limit))
            $limitClause = sprintf('%1$s, %2$s', $limit[0], $limit[1]);
        else
            $limitClause = strval($limit);

        $sql = trim("
SELECT SQL_CALC_FOUND_ROWS
    `reposts`.`id`,
    `reposts`.`created`,
    'repost' as `type`
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
UNION ALL
SELECT
    `comments`.`id`,
    `comments`.`created`,
    'comment' as `type`
FROM `records` as `comments`
INNER JOIN `records` as `parents` ON
    `parents`.`type` = 'post' AND
    `parents`.`status` = 'publish' AND
    `parents`.`affiliated_to` IS NULL AND
    `parents`.`user` = :user
WHERE
    `comments`.`type` = 'comment' AND
    `comments`.`status` = 'publish' AND
    `comments`.`affiliated_to` = `parents`.`id` AND
    `comments`.`user` != :user
UNION ALL
SELECT
    `likes`.`id`,
    `likes`.`created`,
    'like' as `type`
FROM `relationships` as `likes`
INNER JOIN `records` as `parents` ON
    `parents`.`type` = 'post' AND
    `parents`.`status` = 'publish' AND
    `parents`.`affiliated_to` IS NULL AND
    `parents`.`user` = :user
WHERE
    `likes`.`type` = 'l' AND
    `likes`.`object` = `parents`.`id` AND
    `likes`.`subject` != :user
ORDER BY `created` DESC LIMIT {$limitClause}
        ");

        $statement = Database::getProperty('pdo')->prepare($sql);
        $statement->execute([':user' => $current['id']]);

        $total = Database::fetchTotalRows();
        $results = [];

        while (($data = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = $data['id'];
            $type = $data['type'];

            switch ($type) {
                case 'repost':
                case 'comment':
                    $entry = RecordManager::fetchEntry($id);
                    break;

                case 'like':
                    $entry = LikeRelationshipManager::fetchEntry($id);
                    break;

                default:
                    $this->throwInconsistentInternalDataException();
            }

            $results[] = [
                'type' => $type,
                $type => $entry
            ];
        }

        static::setPagingOnResponse($response, $total, $filtered['perPage']);
        return new EmbeddableList($results);
    }
}
