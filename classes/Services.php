<?php
declare(strict_types=1);
namespace UOPF;

use PDO;
use Exception;
use Dotenv\Dotenv;
use UOPF\Routing\Route;
use UOPF\Routing\Router;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;

/**
 * Service Manager
 */
final class Services {
    /**
     * The database manager.
     */
    public readonly Database $database;

    /**
     * The router.
     */
    protected Router $router;

    /**
     * The single instance of service manager.
     */
    protected static self $instance;

    protected function __construct() {
        try {
            $this->database = $this->connectDatabase();
        } catch ( \Exception $exception ) {
            $message = $exception->getMessage();
            static::terminate("Failed to connect to database ($message).");
        }
    }

    public function serve(Request $request): void {
        $dispatcher = new EventDispatcher();
        $controllerResolver = new ControllerResolver();
        $requestStack = new RequestStack();
        $argumentResolver = new ArgumentResolver();

        $kernel = new HttpKernel(
            $dispatcher,
            $controllerResolver,
            $requestStack,
            $argumentResolver
        );

        $request->attributes->set('_controller', [$this, 'getResponse']);
        $response = $kernel->handle($request);

        $response->send();
        $kernel->terminate($request, $response);
    }

    public function getResponse(Request $request): Response {
        if ($matched = $this->getRouter()->match($request)) {
            $controller = $matched->route->controller;
            return $controller();
        } else {
            return $this->getNotFoundResponse();
        }
    }

    public function getHomeResponse(): Response {
        return $this->getNotFoundResponse();
    }

    public function getNotFoundResponse(): Response {
        return new Response('<h1>404 Not Found.</h1>', 404);
    }

    protected function getRouter(): Router {
        if (isset($this->router))
            return $this->router;

        $this->router = new Router();

        $this->router->register(new Route(
            '',
            [$this, 'getHomeResponse']
        ));

        return $this->router;
    }

    public static function isDevelopment(): bool {
        return isset($_ENV['UOPF_ENV']) && $_ENV['UOPF_ENV'] === 'development';
    }

    protected static function connectDatabase(): Database {
        if (!isset($_ENV['UOPF_DB_HOST']))
            throw new Exception('Database host is require.');

        if (!isset($_ENV['UOPF_DB_NAME']))
            throw new Exception('Database name is require.');

        if (!isset($_ENV['UOPF_DB_USERNAME']))
            throw new Exception('Database username is require.');

        if (!isset($_ENV['UOPF_DB_PASSWORD']))
            throw new Exception('Database password is require.');

        return new Database([
            'type' => 'mariadb',
            'host' => $_ENV['UOPF_DB_HOST'],
            'database' => $_ENV['UOPF_DB_NAME'],
            'username' => $_ENV['UOPF_DB_USERNAME'],
            'password' => $_ENV['UOPF_DB_PASSWORD'],

            // Set the charset.
            'charset' => 'utf8mb4',

            // Enable logging in development environment.
            'logging' => static::isDevelopment(),

            // Report database errors via exception.
            'option' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]
        ]);
    }

    public static function configureEnvironment(): void {
        // Load environment variables from files.
        static::loadEnvironmentVariables();

        // Set the error reporting level.
        if (static::isDevelopment())
            error_reporting(E_ALL);
        else
            error_reporting(0);

        // Set the default time zone.
        if (isset($_ENV['TZ']))
            date_default_timezone_set($_ENV['TZ']);
    }

    protected static function loadEnvironmentVariables(): void {
        $development = Dotenv::createImmutable(ROOT . 'variable/');
        $development->safeLoad();

        $production = Dotenv::createImmutable(ROOT);
        $production->safeLoad();
    }

    protected static function terminate( string $message ): never {
        if (in_array(\PHP_SAPI, ['cli', 'phpdbg'], true)) {
            echo "ERROR: {$message}\n";
            exit(1);
        } else {
            if (!headers_sent())
                header('HTTP/1.1 500 Internal Server Error', true, 500);

            if (static::isDevelopment())
                die("<h1>ERROR: {$message}</h1>");
            else
                die('<h1>ERROR: Internal error occurred.</h1>');
        }
    }

    public static function serveRequest(): void {
        $request = Request::createFromGlobals();
        static::getInstance()->serve( $request );
    }

    public static function getInstance(): static {
        if (!isset(static::$instance))
            static::$instance = new static();

        return static::$instance;
    }
}
