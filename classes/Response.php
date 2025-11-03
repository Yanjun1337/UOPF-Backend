<?php
declare(strict_types=1);
namespace UOPF;

use UOPF\Request;
use UOPF\Routing\Matched;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class Response extends SymfonyResponse {
    public function redirect(string $location, int $code = 302): void {
        $this->setStatusCode($code);
        $this->headers->set('Location', $location);
    }

    public function canonicalize(Matched $matched, Request $request): bool {
        if (!$matched->route->isDirectory)
            return false;

        if ($matched->parameters['trailingSlash'] === '/')
            return false;

        $components = explode('?', $request->getRequestUri());
        $components[0] .= '/';

        $this->redirect(implode('?', $components), 301);
        return true;
    }
}
