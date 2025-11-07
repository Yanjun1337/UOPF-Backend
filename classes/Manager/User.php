<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Model\User as Model;
use UOPF\Facade\Database;
use UOPF\Validator\StringValidator;
use UOPF\Validator\IntegerValidator;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Exception\ValidationException;
use UOPF\Exception\UserRegistrationException;
use UOPF\Exception\DuplicateUniqueColumnException;
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

    public function parseEntryFromToken(string $token): ?Model {
        $components = explode('|', $token);

        if (count($components) !== 4)
            return null;

        $components = array_combine([
            'id',
            'expirationTime',
            'seed',
            'secret'
        ], $components);

        try {
            $filtered = (new DictionaryValidator([
                'id' => new DictionaryValidatorElement(
                    validator: new IntegerValidator(
                        min: 1
                    )
                ),

                'expirationTime' => new DictionaryValidatorElement(
                    validator: new IntegerValidator(
                        min: time()
                    )
                ),

                'seed' => new DictionaryValidatorElement(
                    validator: new StringValidator(
                        min: 32,
                        max: 32,
                        regex: '[a-f0-9]+'
                    )
                ),

                'secret' => new DictionaryValidatorElement(
                    validator: new StringValidator(
                        min: 64,
                        max: 64,
                        regex: '[a-f0-9]+'
                    )
                )
            ]))->filter(array_combine([
                'id',
                'expirationTime',
                'seed',
                'secret'
            ], $components));
        } catch (ValidationException) {
            return null;
        }

        if (!$user = $this->fetchEntry($filtered['id']))
            return null;

        $expectedToken = $user->calculateToken(
            $filtered['expirationTime'],
            $filtered['seed']
        );

        if (hash_equals($token, $expectedToken))
            return $user;
        else
            return null;
    }

    public function register(
        string $username,
        string $displayName,
        string $email,
        string $passwordHash,
        string $role = 'normal'
    ): Model {
        $data = [
            'role' => $role,
            'username' => $username,
            'display_name' => $displayName,
            'email' => $email,
            'registered' => Database::getCurrentTime()
        ];

        $metadata = [
            'password' => $passwordHash,
            'unreadNotifications' => 0
        ];

        return Database::transaction(function () use (&$data, &$metadata) {
            // 1. Insert the entry into the database.
            try {
                $created = $this->createEntry($data);
            } catch (DuplicateUniqueColumnException $exception) {
                switch ($exception->column) {
                    case 'username':
                        throw new UserRegistrationException('This username is already used by another user.');

                    case 'display_name':
                        throw new UserRegistrationException('This display name is already used by another user.');

                    case 'email':
                        throw new UserRegistrationException('This email is already used by another user.');

                    default:
                        throw $exception;
                }
            }

            // 2. Insert metadata into the database.
            foreach ($metadata as $name => $value)
                $created->setMetadata($name, $value);

            // 3. Return the created user.
            return $created;
        });
    }

    public function createPasswordHash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
