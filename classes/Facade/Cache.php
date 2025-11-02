<?php
declare(strict_types=1);
namespace UOPF\Facade;

use UOPF\Cache as Service;
use UOPF\Facade;
use UOPF\Services;

/**
 * Facade for Cache
 */
final class Cache extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->cache;
    }
}
