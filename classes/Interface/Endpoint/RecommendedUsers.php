<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Utilities;
use UOPF\RangedIndexedTable;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Relationship\User as UserRelationshipManager;
use UOPF\Interface\Endpoint;

/**
 * Recommended Users
 */
final class RecommendedUsers extends Endpoint {
    public function read(Response $response): array {
        $total = 3;

        $users = [
            'random' => [
                'where' => [
                    'LIMIT' => $total,
                    'ORDER' => Database::raw('RAND()')
                ],

                'rank'  => 1,
                'cause' => 'Random'
            ],

            'likes' => [
                'where' => [
                    'LIMIT' => 1,
                    'ORDER' => ['_likes' => 'DESC']
                ],

                'rank'  => 2,
                'cause' => 'Most likes'
            ],

            'reposts' => [
                'where' => [
                    'LIMIT' => 1,
                    'ORDER' => ['_reposts' => 'DESC']
                ],

                'rank'  => 2,
                'cause' => 'Most reposts'
            ]
        ];

        if ($current = $this->request->user) {
            $sql = trim('
SELECT DISTINCT `object`
FROM `relationships`
WHERE `type` = :_user AND `subject` IN (
        SELECT `object` as `subject`
        FROM `relationships`
        WHERE `type` = :_user AND `subject` = :user
    UNION ALL
        SELECT `subject`
        FROM `relationships`
        WHERE `type` = :_user AND `object` = :user
)
            ');

            $parameters = [
                ':user' => $current['id'],
                ':_user' => 'u'
            ];

            $statement = Database::getProperty('pdo')->prepare($sql);
            $statement->execute($parameters);

            $relatives = $statement->fetchAll();
            $relatives = Utilities::arrayColumn($relatives, 'object');

            if ($relatives) {
                $users['relational'] = [
                    'where' => [
                        'LIMIT' => $total,
                        'ORDER' => Database::raw('RAND()'),
                        'AND' => ['id' => $relatives]
                    ],

                    'rank'  => 3,
                    'cause' => 'Followed by your friends'
                ];
            }

            $where = [
                'type' => 'user',
                'subject' => $current['id']
            ];

            $followings = UserRelationshipManager::queryEntries($where)->entries;
            $followings = Utilities::arrayColumn($followings, 'object');

            $excluded = $followings;
            $excluded[] = $current['id'];
        } else {
            $excluded = [];
        }

        usort($users, function (array $lhs, array $rhs): int {
            if ($lhs['rank'] > $rhs['rank'])
                return -1;
            elseif ($lhs['rank'] < $rhs['rank'])
                return 1;
            else
                return 0;
        });

        $querying = [];
        $results = [];

        foreach ($users as $name => $settings) {
            if ($excluded)
                $settings['where']['AND']['id[!]'] = $excluded;

            if ($entries = UserManager::queryEntries($settings['where'])->entries) {
                $querying[] = [
                    'items' => $entries,
                    'rank' => $settings['rank'],
                    'cause' => $settings['cause']
                ];

                $excluded = array_merge(
                    $excluded,
                    Utilities::arrayColumn($entries, 'id')
                );
            }
        }

        for ($count = $total; $count > 0; --$count) {
            if (!$querying)
                break;

            $table = new RangedIndexedTable($querying);
            $random = $table->get(rand(1, $table->maximum));

            $user = array_shift($querying[$random]['items']);
            $cause = $querying[$random]['cause'];

            $results[] = compact(
                'user',
                'cause'
            );

            if (!$querying[$random]['items'])
                unset($querying[$random]);
        }

        return $results;
    }
}
