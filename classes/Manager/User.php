<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Exception;
use UOPF\DatabaseLockType;
use UOPF\Model\User as Model;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use UOPF\Facade\Manager\Relationship\User as UserRelationshipManager;
use UOPF\Facade\Manager\Relationship\Like as LikeRelationshipManager;
use UOPF\Facade\Manager\Relationship\Dislike as DislikeRelationshipManager;
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
    protected array $indexes = [
        'email' => [
            'email'
        ],

        'username' => [
            'username'
        ]
    ];

    public function getTableName(): string {
        return 'users';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function pushNotificationToLockedUser(Model $locked): void {
        if ($lockedMetadata = UserMetadataManager::fetchDirectly('unreadNotifications', $locked->data['id'], DatabaseLockType::write)) {
            $current = $lockedMetadata->getDecodedValue();
            UserMetadataManager::setLocked($lockedMetadata, $current + 1);
        } else {
            UserMetadataManager::add('unreadNotifications', 1, $locked->data['id']);
        }
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

    public function unregisterLocked(Model $locked): void {
        $logs = [];

        $records = RecordManager::queryEntries([
            'user' => $locked['id'],
            'status[!]' => 'trashed'
        ]);

        foreach ($records->entries as $record) {
            if ($lockedRecord = RecordManager::fetchEntryDirectly($record['id'], lock: DatabaseLockType::write)) {
                RecordManager::trashLocked($lockedRecord);

                $logs[] = [
                    'type' => 'record',
                    'id' => $record['id'],
                    'status' => $record['status']
                ];
            } else {
                throw new Exception('Failed to fetch entry.');
            }
        }

        $followings = UserRelationshipManager::queryEntries([
            'type' => UserRelationshipManager::getProperty('type'),
            'subject' => $locked['id']
        ]);

        foreach ($followings->entries as $relationship) {
            if ($lockedRelationship = UserRelationshipManager::fetchEntryDirectly($relationship['id'], lock: DatabaseLockType::write)) {
                UserRelationshipManager::deleteLockedEntry($lockedRelationship);
                $this->incrementLockedEntryField($locked, '_followings', -1);

                if ($lockedObject = $this->fetchEntryDirectly($relationship['object'], lock: DatabaseLockType::write))
                    $this->incrementLockedEntryField($lockedObject, '_followers', -1);
                else
                    throw new Exception('Failed to fetch user.');

                $logs[] = [
                    'type' => 'following',
                    'object' => $relationship['object']
                ];
            } else {
                throw new Exception('Failed to fetch relationship.');
            }
        }

        $followers = UserRelationshipManager::queryEntries([
            'type' => UserRelationshipManager::getProperty('type'),
            'object' => $locked['id']
        ]);

        foreach ($followers->entries as $relationship) {
            if ($lockedRelationship = UserRelationshipManager::fetchEntryDirectly($relationship['id'], lock: DatabaseLockType::write)) {
                UserRelationshipManager::deleteLockedEntry($lockedRelationship);
                $this->incrementLockedEntryField($locked, '_followers', -1);

                if ($lockedSubject = $this->fetchEntryDirectly($relationship['subject'], lock: DatabaseLockType::write))
                    $this->incrementLockedEntryField($lockedSubject, '_followings', -1);
                else
                    throw new Exception('Failed to fetch user.');

                $logs[] = [
                    'type' => 'follower',
                    'subject' => $relationship['subject']
                ];
            } else {
                throw new Exception('Failed to fetch relationship.');
            }
        }

        $likes = LikeRelationshipManager::queryEntries([
            'type' => LikeRelationshipManager::getProperty('type'),
            'subject' => $locked['id']
        ]);

        foreach ($likes->entries as $like) {
            if ($lockedLike = LikeRelationshipManager::fetchEntryDirectly($like['id'], lock: DatabaseLockType::write)) {
                LikeRelationshipManager::deleteLockedEntry($lockedLike);

                if ($lockedRecord = RecordManager::fetchEntryDirectly($lockedLike['object'], lock: DatabaseLockType::write)) {
                    RecordManager::incrementLockedEntryField($lockedRecord, '_likes', -1);

                    if ($lockedUser = $this->fetchEntryDirectly($lockedRecord['user'], lock: DatabaseLockType::write))
                        $this->incrementLockedEntryField($lockedUser, '_likes', -1);
                    else
                        throw new Exception('Failed to fetch user.');
                } else {
                    throw new Exception('Failed to fetch record.');
                }

                $logs[] = [
                    'type' => 'like',
                    'object' => $like['object']
                ];
            } else {
                throw new Exception('Failed to fetch like.');
            }
        }

        $dislikes = DislikeRelationshipManager::queryEntries([
            'type' => DislikeRelationshipManager::getProperty('type'),
            'subject' => $locked['id']
        ]);

        foreach ($dislikes->entries as $dislike) {
            if ($lockedDislike = DislikeRelationshipManager::fetchEntryDirectly($dislike['id'], lock: DatabaseLockType::write)) {
                DislikeRelationshipManager::deleteLockedEntry($lockedDislike);

                if ($lockedRecord = RecordManager::fetchEntryDirectly($lockedDislike['object'], lock: DatabaseLockType::write))
                    RecordManager::incrementLockedEntryField($lockedRecord, '_dislikes', -1);
                else
                    throw new Exception('Failed to fetch record.');

                $logs[] = [
                    'type' => 'dislike',
                    'object' => $dislike['object']
                ];
            } else {
                throw new Exception('Failed to fetch dislike.');
            }
        }

        $allMetadata = UserMetadataManager::queryEntries([
            'group' => UserMetadataManager::getProperty('group'),
            'affiliated_to' => $locked['id']
        ]);

        foreach ($allMetadata->entries as $metadata) {
            if ($lockedMetadata = UserMetadataManager::fetchEntryDirectly($metadata['id'], lock: DatabaseLockType::write)) {
                UserMetadataManager::deleteLockedEntry($lockedMetadata);

                $logs[] = [
                    'type' => 'metadata',
                    'name' => $lockedMetadata['name'],
                    'value' => $lockedMetadata->getDecodedValue()
                ];
            } else {
                throw new Exception('Failed to fetch metadata.');
            }
        }

        $this->deleteLockedEntry($locked);

        $time = Database::getCurrentTime();

        CaseManager::createEntry([
            'type' => 'unregistered',
            'status' => 'completed',
            'tag' => "unregistered-{$locked['id']}",

            'created' => $time,
            'modified' => $time,

            'metadata' => [
                'data' => $locked->data,
                'logs' => $logs
            ]
        ]);
    }

    public function createPasswordHash(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }
}
