<?php
declare(strict_types=1);
namespace UOPF\Facade\Manager\Relationship;

use UOPF\Facade;
use UOPF\Services;
use UOPF\Manager\Relationship as Service;

/**
 * Facade for Like Relationship Manager
 */
final class Like extends Facade {
    public static function getInstance(): Service {
        return Services::getInstance()->likeRelationshipManager;
    }
}
