<?php
declare(strict_types=1);
namespace UOPF;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class Request extends SymfonyRequest {
    public function getRoute(): string {
        $uri = explode('?', $this->getRequestUri())[0];
        return substr($uri, 1);
    }
}
