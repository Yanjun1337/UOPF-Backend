<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Request;
use UOPF\Services;
use UOPF\Response;
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

        try {
            if (!$matched = $router->match($request))
                throw new Exception('Not found.', 404);

            if (!$response->canonicalize($matched, $request)) {
                $controller = $matched->route->controller;
                $data = $controller($request, $response, $matched->parameters);

                $content = json_encode($data);
                $response->setContent($content);
            }
        } catch (Exception $exception) {
            $exception->renderTo($response);
        } catch (\Exception $exception) {
            if (Services::isDevelopment()) {
                $exceptions = [];

                do {
                    $exceptions[] = [
                        'message' => $exception->getMessage(),
                        'code' => $exception->getCode()
                    ];

                    $exception = $exception->getPrevious();
                } while (isset($exception));

                $data = compact('exceptions');
            } else {
                $data = [];
            }

            $interfaceException = new Exception('Internal server error.', 500, $data);
            $interfaceException->renderTo($response);
        }

        $exposed = [];

        foreach ($response->headers->allPreserveCase() as $name => $value)
            if (strpos($name, 'X-API-') === 0)
                $exposed[] = $name;

        if ($exposed) {
            $response->headers->set(
                'Access-Control-Expose-Headers',
                implode(', ', $exposed)
            );
        }

        return $response;
    }

    public function getRouter(): Router {
        if (isset($this->router))
            return $this->router;

        $user = '(?P<id>[1-9]+\d*|current)';
        $prefix = 'UOPF\\Interface\Endpoint\\';

        $this->router = new Router($this->root);

        $this->router->register(new Route(
            uri: 'session',
            controller: ["{$prefix}Session", 'serve'],
            isDirectory: true
        ));

        $this->router->register(new Route(
            uri: 'posts/upload',
            controller: ["{$prefix}PostsUpload", 'serve'],
            isDirectory: true
        ));

        $this->router->register(new Route(
            uri: "users/{$user}/meta/(?P<name>[^/]+)",
            controller: ["{$prefix}UsersMetadata", 'serve'],
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
