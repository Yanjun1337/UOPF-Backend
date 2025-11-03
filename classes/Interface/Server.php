<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Request;
use UOPF\Services;
use UOPF\Response;
use UOPF\Exception;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Routing\Route;
use UOPF\Routing\Router;

/**
 * API Server
 */
final class Server {
    /**
     * The router.
     */
    protected readonly Router $router;

    public function __construct(
        /**
         * Root URI.
         */
        protected string $root = ''
    ) {}

    public function getResponse(Request $request): Response {
        $headers = [
            'Content-Type' => sprintf(
                '%1$s; charset=%2$s',
                'application/json',
                'UTF-8'
            ),

            'Expires' => 'Wed, 11 Jan 1984 05:00:00 GMT',
            'Cache-Control' => 'no-cache, must-revalidate, max-age=0',
            'Last-Modified' => null,
            'X-Robots-Tag' => 'noindex',

            'Access-Control-Allow-Methods' => implode(', ', [
                'OPTIONS',
                'GET',
                'POST',
                'PUT',
                'PATCH',
                'DELETE'
            ]),

            'Access-Control-Allow-Headers' => implode(', ', [
                'Authorization',
                'Content-Type',
                'X-API-Embed',
                'X-API-Token'
            ])
        ];

        if (Services::isDevelopment()) {
            $headers['Access-Control-Allow-Origin'] = '*';
            $headers['Access-Control-Allow-Credentials'] = 'true';
        } elseif (
            ($origin = $request->headers->get('origin')) &&
            (in_array($origin, static::getAllowedOrigins()))
        ) {
            $headers['Access-Control-Allow-Origin'] = $origin;
            $headers['Access-Control-Allow-Credentials'] = 'true';
        }

        $response = new Response(headers: $headers);
        $router = $this->getRouter();

        if ($matched = $router->match($request)) {
            if ($response->canonicalize($matched, $request))
                return $response;

            $controller = $matched->route->controller;

            try {
                $content = $controller($request, $response);
                $response->setContent(json_encode($content));
            } catch (Exception $exception) {
                $response->setStatusCode(500);
            }
        }

        return $response;
    }

    public function getRouter(): Router {
        if (isset($this->router))
            return $this->router;

        $this->router = new Router($this->root);
        $prefix = 'UOPF\\Interface\Endpoint\\';

        $this->router->register(new Route(
            uri: 'session',
            controller: ["{$prefix}Session", 'serve'],
            isDirectory: true
        ));

        return $this->router;
    }

    protected static function getAllowedOrigins(): array {
        $specified = SystemMetadataManager::get('allowedOrigins');
        $specified = array_filter(explode("\n", $specified));

        $frontend = parse_url(SystemMetadataManager::get('frontendAddress'));
        $backend = parse_url(SystemMetadataManager::get('backendAddress'));

        return array_unique(array_merge($specified, [
            'http://' . $frontend['host'],
            'https://' . $frontend['host'],

            'http://' . $backend['host'],
            'https://' . $backend['host']
        ]));
    }
}
