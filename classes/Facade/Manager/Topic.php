<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Topic as Service;

/**
 * Facade for Topic Manager
 */
final class Topic extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->topicManager;
    }
}
