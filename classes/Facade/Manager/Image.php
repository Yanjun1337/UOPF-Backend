<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Image as Service;

/**
 * Facade for Image Manager
 */
final class Image extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->imageManager;
    }
}
