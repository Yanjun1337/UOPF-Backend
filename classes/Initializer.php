<?php
declare(strict_types=1);
namespace UOPF;

use PDOException;
use UOPF\Facade\Cache;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Metadata\System as SystemMetadata;
use UOPF\Exception\DatabaseException;
use PragmaRX\Random\Random;

abstract class Initializer {
    public static function initialize(
        string $email,
        string $username,
        string $password
    ): void {
        // 1. Flush cache.
        Cache::flush();

        // 2. Create tables in the database.
        try {
            Database::exec(static::getSchema());
        } catch (PDOException $exception) {
            throw DatabaseException::createFromPDOException($exception);
        } finally {
            Cache::flush();
        }

        // 3. Create system metadata.
        foreach (static::getInitialMetadata() as $name => $value)
            SystemMetadata::add($name, $value);

        // 4. Create the default administrator user.
        UserManager::register(
            role: 'administrator',
            username: $username,
            displayName: $username,
            email: $email,
            passwordHash: UserManager::createPasswordHash($password)
        );

        // 5. Flush cache.
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

CREATE TABLE `users` (
    `id` bigint(20) unsigned NOT NULL auto_increment,
    `role` varchar(20) NOT NULL default 'normal',
    `username` varchar(128) NOT NULL,
    `display_name` varchar(128) NOT NULL,
    `domain` varchar(128),
    `email` varchar(128) NOT NULL,
    `description` text,
    `registered` datetime NOT NULL,

    `_followings` bigint(20) unsigned NOT NULL default 0,
    `_followers` bigint(20) unsigned NOT NULL default 0,
    `_posts` bigint(20) unsigned NOT NULL default 0,
    `_likes` bigint(20) unsigned NOT NULL default 0,
    `_reposts` bigint(20) unsigned NOT NULL default 0,

    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `display_name` (`display_name`),
    UNIQUE KEY `domain` (`domain`),
    UNIQUE KEY `email` (`email`)
) DEFAULT CHARACTER SET {$charset} COLLATE {$collation};

CREATE TABLE `images` (
    `id` bigint( 20 ) unsigned NOT NULL auto_increment,
    `status` varchar( 20 ) NOT NULL,
    `file` varchar( 128 ) NOT NULL,
    `created` datetime NOT NULL,
    `modified` datetime NOT NULL,
    `user` bigint( 20 ) unsigned,
    `record` bigint( 20 ) unsigned,
    `position` int( 11 ) unsigned,
    `metadata` longtext NOT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `file` (`file`),
    KEY `record` (`status`, `record`, `position`)
) DEFAULT CHARACTER SET {$charset} COLLATE {$collation};

CREATE TABLE `cases` (
	`id` bigint(20) unsigned NOT NULL auto_increment,
	`user` bigint(20) unsigned,
	`tag` varchar(191) NOT NULL,
	`created` datetime NOT NULL,
	`modified` datetime NOT NULL,
	`type` varchar(20) NOT NULL,
	`status` varchar(20) NOT NULL,
	`metadata` longtext,

	PRIMARY KEY (`id`),
	UNIQUE KEY `tag` (`type`, `tag`)
) DEFAULT CHARACTER SET {$charset} COLLATE {$collation};
        ");
    }

    protected static function getInitialMetadata(): array {
        $random = new Random();
        $key = $random->pattern('[a-zA-Z0-9_]')->size(256)->get();

        return [
            'frontendAddress' => 'https://www.example.com/',
            'backendAddress' => 'https://api.example.com/',
            'initialized' => time(),
            'tokenKey' => $key
        ];
    }
}
