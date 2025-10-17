<?php
declare(strict_types=1);
namespace UOPF\Routing;

/**
 * Route
 */
final class Route {
    /**
     * Controller.
     */
    public readonly \Closure $controller;

    public function __construct(
        /**
         * The URI.
         */
        public readonly string $uri,

        callable $controller,

        /**
         * Whether the URI is a directory.
         */
        public readonly bool $isDirectory = false
    ) {
        $this->controller = \Closure::fromCallable($controller);
    }
}
