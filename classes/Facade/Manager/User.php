<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\User as Service;

/**
 * Facade for User Manager
 */
final class User extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->userManager;
    }
}
