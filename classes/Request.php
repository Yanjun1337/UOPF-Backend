<?php
declare(strict_types=1);
namespace UOPF;

use UOPF\Model\User;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

final class Request extends SymfonyRequest {
    /**
     * The currently authenticated user.
     */
    protected(set) ?User $user = null;

    public function getRoute(): string {
        $uri = explode('?', $this->getRequestUri())[0];
        return substr($uri, 1);
    }

    public function setUser(?User $user): void {
        $this->user = $user;
    }
}
