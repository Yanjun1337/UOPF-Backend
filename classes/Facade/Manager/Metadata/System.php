<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager\Metadata;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Metadata as Service;

/**
 * Facade for System Metadata Manager
 */
final class System extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->systemMetadataManager;
    }
}
