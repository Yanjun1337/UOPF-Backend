<?php
declare(strict_types=1);
namespace UOPF\Routing;

use UOPF\Request;

/**
 * Router
 */
final class Router {
    /**
     * Routes
     */
    protected array $routes = [];

    public function __construct(
        /**
         * Root URI.
         */
        protected string $root = ''
    ) {}

    /**
     * Register a route.
     */
    public function register(Route $route): void {
        $this->routes[] = $route;
    }

    /**
     * Match a request.
     */
    public function match(Request $request): ?Matched {
        foreach ($this->routes as $route) {
            $rootRegex = preg_quote($this->root, '#');
            $regex = $rootRegex . $route->uri;

            if ($route->isDirectory)
                $regex = '#^' . $regex . '(?P<trailingSlash>/?)$#';
            else
                $regex = '#^' . $regex. '$#';

            if (!preg_match_all($regex, $request->getRoute(), $matches))
                continue;

            $parameters = static::extractMatchedParameters($matches);
            return new Matched($route, $parameters);
        }

        return null;
    }

    protected static function extractMatchedParameters(array $matches): array {
        $parameters = [];

        foreach ($matches as $name => $value)
            if (is_string($name))
                $parameters[$name] = $value[0];

        return $parameters;
    }
}
