<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Record as Service;

/**
 * Facade for Record Manager
 */
final class Record extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->recordManager;
    }
}
