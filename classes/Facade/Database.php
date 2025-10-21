<?php
declare(strict_types=1);
namespace UOPF\Facade;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Database as Service;

/**
 * Facade
 */
final class Database extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->database;
    }
}
