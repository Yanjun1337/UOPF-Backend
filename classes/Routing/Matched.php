<?php
declare(strict_types=1);
namespace UOPF\Routing;

/**
 * Matched Route
 */
final class Matched {
    public function __construct(
        /**
         * The route.
         */
        public readonly Route $route,

        /**
         * Parameters in URI.
         */
        public readonly array $parameters
    ) {}
}
