<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;

final class Topic extends Model {
    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'label' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'created_from' => ModelFieldType::integer,
            '_records' => ModelFieldType::integer
        ];
    }
}
