<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Utilities;
use UOPF\DatabaseLockType;
use UOPF\Model\Topic as Model;
use UOPF\Facade\Database;

/**
 * Topic Manager
 */
final class Topic extends Manager {
    public function getTableName(): string {
        return 'topics';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function engageRecordIn(array $topics, int $record): void {
        Database::transaction(function () use (&$topics, &$record) {
            $time = Database::getCurrentTime();

            foreach ($topics as $topic) {
                if ($lockedTopic = $this->fetchEntryDirectly($topic, 'label', lock: DatabaseLockType::write)) {
                    $this->updateLockedEntry($lockedTopic, [
                        'modified' => $time,
                        '_records' => $lockedTopic['_records'] + 1
                    ]);
                } else {
                    $this->createEntry([
                        'label' => $topic,
                        'created' => $time,
                        'modified' => $time,
                        'created_from' => $record,
                        '_records' => 1
                    ]);
                }
            }
        });
    }

    public function extractFromText(string $text): array {
        preg_match_all('/#([^\s#@]{1,128})/', $text, $matches);
        return array_values(array_unique($matches[1]));
    }

    public function extractFromHTML(string $html): array {
        $topics = [];

        foreach (Utilities::eachText($html) as $text)
            $topics = array_merge($topics, $this->extractFromText($text));

        return array_values(array_unique($topics));
    }
}
