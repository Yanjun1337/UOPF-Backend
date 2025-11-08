<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;

final class Record extends Model {
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
