<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Facade
 */
abstract class Facade {
    /**
     * Returns the instance that can be accessed via the facade.
     */
    abstract public static function getInstance(): object;

    /**
     * Calls a static method.
     */
    public static function __callStatic(string $name, array $arguments): mixed {
        $callback = [static::getInstance(), $name];
        return $callback(...$arguments);
    }
}
