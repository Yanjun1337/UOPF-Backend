<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Manager\Topic as TopicManager;
use UOPF\Interface\Endpoint;

/**
 * Top Topics
 */
final class TopTopics extends Endpoint {
    public function read(Response $response): array {
        $where = [
            'ORDER' => ['_records' => 'DESC'],
            'LIMIT' => 7
        ];

        $retrieved = TopicManager::queryEntries($where);
        $results = [];

        foreach ($retrieved->entries as $topic) {
            $results[] = [
                'label' => $topic['label'],
                'records' => $topic['_records']
            ];
        }

        return $results;
    }
}
