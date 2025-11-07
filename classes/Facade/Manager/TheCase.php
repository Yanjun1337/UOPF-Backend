<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\TheCase as Service;

/**
 * Facade for Case Manager
 */
final class TheCase extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->caseManager;
    }
}
