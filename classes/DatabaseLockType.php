<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Database Lock Type
 */
enum DatabaseLockType {
    case read;
    case write;
}
