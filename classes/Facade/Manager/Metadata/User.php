<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager\Metadata;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Metadata as Service;

/**
 * Facade for User Metadata Manager
 */
final class User extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->userMetadataManager;
    }
}
