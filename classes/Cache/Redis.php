<?php
declare(strict_types=1);
namespace UOPF\Cache;

use Redis as PHPRedis;
use UOPF\Cache;
use UOPF\Exception;

/**
 * Redis-based Cache Manager
 */
final class Redis extends Cache {
    /**
     * The instance of Redis.
     */
    public readonly PHPRedis $redis;

    public function __construct(
        string $host,
        int $port = 6379,
        null|string|array $password = null,
        ?int $database = null
    ) {
        // 1. Check whether the PHP extension of Redis is installed.
        if (!class_exists('Redis'))
            throw new Exception('PHP Redis extension is required.');

        // 2. Create the instance of Redis.
        $this->redis = new PHPRedis();

        // 3. Connect to Redis server.
        if (!$this->redis->connect($host, $port))
            throw new Exception('Failed to connect to Redis server.');

        // 4. Authenticate the connection.
        if ($password !== null && !$this->redis->auth($password))
            throw new Exception('Failed to authenticate the connection.');

        // 5. Select the specified database.
        if ($database !== null && !$this->redis->select($database))
            throw new Exception('Failed to select the specified database.');
    }

    public function get(string $key): mixed {
        $value = $this->redis->get($key);

        if ($value === false)
            return null;
        elseif ($value === null)
            return false;
        else
            return $value;
    }

    public function set(string $key, mixed $value): void {
        if ( $value === false )
            $value = null;
        elseif ( $value === null )
            $value = false;

        if ($this->redis->set($key, $value) !== true)
            throw new Exception( 'Failed to set the entry.' );
    }

    public function remove(string $key): void {
        if ($this->redis->del($key) === false)
            throw new Exception( 'Failed to remove the entry.' );
    }

    public function flush(): void {
        if ($this->redis->flushDb() !== true)
            throw new Exception( 'Failed to flush the cache pool.' );
    }

    public function isPersistent(): bool {
        return true;
    }
}
