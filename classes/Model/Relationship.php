<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;

final class Relationship extends Model {
    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'type' => ModelFieldType::string,
            'subject' => ModelFieldType::integer,
            'object' => ModelFieldType::integer,
            'created' => ModelFieldType::time
        ];
    }
}
