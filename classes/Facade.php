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
     * Returns the value of a property.
     */
    public static function getProperty(string $name): mixed {
        return static::getInstance()->$name;
    }

    /**
     * Calls a static method.
     */
    public static function __callStatic(string $name, array $arguments): mixed {
        $callback = [static::getInstance(), $name];
        return $callback(...$arguments);
    }
}
