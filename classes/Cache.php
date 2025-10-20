<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Cache Manager
 */
abstract class Cache {
    /**
     * Retrieves an entry by its key.
     */
    abstract public function get(string $key): mixed;

    /**
     * Sets an entry by its key.
     */
    abstract public function set(string $key, mixed $value): void;

    /**
     * Deletes an entry by its key.
     */
    abstract public function remove(string $key): void;

    /**
     * Deletes all entries.
     */
    abstract public function flush(): void;

    /**
     * Checks whether the engine is able to preserve the cache between connections.
     */
    abstract public function isPersistent(): bool;
}
