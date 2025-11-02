<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Metadata Type
 */
enum MetadataType: string {
    case string = 's';
    case integer = 'i';
    case float = 'f';
    case boolean = 'b';
    case serialized = 'o';
}
