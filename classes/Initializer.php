<?php
declare(strict_types=1);
namespace UOPF;

use PDOException;
use UOPF\Facade\Cache;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Metadata\System as SystemMetadata;
use UOPF\Exception\DatabaseException;

abstract class Initializer {
    public static function initialize(): void {
        // 1. Flush cache.
        Cache::flush();

        // 2. Create tables in the database.
        try {
            Database::exec(static::getSchema());
        } catch (PDOException $exception) {
            throw DatabaseException::createFromPDO($exception);
        } finally {
            Cache::flush();
        }

        // 3. Create system metadata.
        SystemMetadata::add('initialized', time());

        // 4. Flush cache.
        Cache::flush();
    }

    protected static function getSchema(): string {
        $charset = 'utf8mb4';
        $collation = 'uca1400_as_ci';

        return trim("
CREATE TABLE `metadata` (
    `id` bigint(20) unsigned NOT NULL auto_increment,
    `group` varchar(20) NOT NULL,
    `affiliated_to` bigint(20) unsigned default 0,
    `name` varchar(128) NOT NULL,
    `value` longtext NOT NULL,
    `type` varchar(10) NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `specific` (`group`, `affiliated_to`, `name`)
) DEFAULT CHARACTER SET {$charset} COLLATE {$collation};
        ");
    }
}
