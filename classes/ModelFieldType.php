<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Field Type of Entry Data Model
 */
enum ModelFieldType {
    case string;
    case integer;
    case float;
    case boolean;
    case serialized;
    case time;
}
