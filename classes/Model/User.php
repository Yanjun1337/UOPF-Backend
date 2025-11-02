<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;

final class User extends Model {
    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'role' => ModelFieldType::string,
            'username' => ModelFieldType::string,
            'display_name' => ModelFieldType::string,
            'domain' => ModelFieldType::string,
            'email' => ModelFieldType::string,
            'description' => ModelFieldType::string,
            'registered' => ModelFieldType::date,

            '_followings' => ModelFieldType::integer,
            '_followers'  => ModelFieldType::integer,
            '_posts' => ModelFieldType::integer,
            '_likes' => ModelFieldType::integer,
            '_reposts' => ModelFieldType::integer
        ];
    }
}
