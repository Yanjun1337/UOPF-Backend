<?php
declare(strict_types=1);
namespace UOPF\Facade;

use UOPF\Facade;
use UOPF\Setting\Settings as Service;

/**
 * Facade for Settings
 */
final class Settings extends Facade {
    public static function getInstance(): Service {
        return Service::getInstance();
    }
}
