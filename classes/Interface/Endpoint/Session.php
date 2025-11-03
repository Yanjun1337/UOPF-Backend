<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Interface\Endpoint;

/**
 * Session
 */
final class Session extends Endpoint {
    public function read(Response $response): array {
        return [
            'frontend' => SystemMetadataManager::get('frontendAddress'),
            'backend' => SystemMetadataManager::get('backendAddress')
        ];
    }
}
