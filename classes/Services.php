<?php
declare(strict_types=1);
namespace UOPF;

use Dotenv\Dotenv;

/**
 * Services
 */
final class Services {
    public static function configureEnvironment(): void {
        // Load environment variables from files.
        static::loadEnvironmentVariables();
    }

    protected static function loadEnvironmentVariables(): void {
        $development = Dotenv::createImmutable(ROOT . 'variable/');
        $development->safeLoad();

        $production = Dotenv::createImmutable(ROOT);
        $production->safeLoad();
    }
}
