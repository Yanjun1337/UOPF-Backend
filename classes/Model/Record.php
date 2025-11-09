<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;
use UOPF\Facade\Manager\Topic as TopicManager;

final class Record extends Model {
    public function canBeEditedBy(User $user): bool {
        if ($user->isAdministrator())
            return true;

        if ($this->data['status'] == 'publish')
            return $this->data['user'] === $user['id'];

        return false;
    }

    public function isLong(): bool {
        return isset($this->data['title']);
    }

    public function extractTopics(): array {
        if ($this->isLong())
            return TopicManager::extractFromHTML($this->data['content']);
        else
            return TopicManager::extractFromText($this->data['content']);
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'user' => ModelFieldType::integer,
            'parent' => ModelFieldType::integer,
            'affiliated_to' => ModelFieldType::integer,
            'title' => ModelFieldType::string,
            'content' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'type' => ModelFieldType::string,
            'status' => ModelFieldType::string,
            'user_agent' => ModelFieldType::string,

            '_likes' => ModelFieldType::integer,
            '_dislikes' => ModelFieldType::integer,
            '_comments' => ModelFieldType::integer,
            '_reposts' => ModelFieldType::integer
        ];
    }
}
