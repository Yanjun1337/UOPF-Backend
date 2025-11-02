<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Model\User as Model;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use const PASSWORD_ARGON2ID;

/**
 * User Manager
 */
final class User extends Manager {
    public function getTableName(): string {
        return 'users';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function register(
        string $role,
        string $username,
        string $displayName,
        string $email,
        string $password
    ): Model {
        $data = [
            'role' => $role,
            'username' => $username,
            'display_name' => $displayName,
            'email' => $email,
            'registered' => Database::getCurrentTime()
        ];

        $metadata = [
            'password' => static::createPasswordHash($password),
            'unreadNotifications' => 0
        ];

        return Database::transaction(function () use (&$data, &$metadata) {
            // 1. Insert the entry into the database.
            $created = $this->createEntry($data);

            // 2. Insert metadata into the database.
            foreach ($metadata as $name => $value)
                UserMetadataManager::add($name, $value, $created['id']);

            // 3. Return the created user.
            return $created;
        });
    }

    protected static function createPasswordHash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
