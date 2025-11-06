<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;

final class TheCase extends Model {
    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'user' => ModelFieldType::integer,
            'tag' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'type' => ModelFieldType::string,
            'status' => ModelFieldType::string,
            'metadata' => ModelFieldType::serialized
        ];
    }
}
