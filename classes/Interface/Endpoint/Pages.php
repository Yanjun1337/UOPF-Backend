<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Utilities;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Interface\Endpoint;

/**
 * Pages
 */
final class Pages extends Endpoint {
    public function read(Response $response): array {
        $pages = [
            'privacy-policy' => 'privacyPolicy',
            'contact' => 'contact',
            'terms-and-conditions' => 'termsAndConditions'
        ];

        $name = $this->query['name'];

        if (!isset($pages[$name]))
            $this->throwNotFoundException();

        $title = SystemMetadataManager::get("page/{$pages[$name]}/title") ?? 'Untitled';
        $content = SystemMetadataManager::get("page/{$pages[$name]}/content") ?? '';

        return [
            'title' => $title,
            'content' => Utilities::renderMarkdown($content)
        ];
    }
}
