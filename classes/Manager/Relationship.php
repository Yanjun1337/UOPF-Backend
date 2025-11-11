<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\DatabaseLockType;
use UOPF\Model\Relationship as Model;
use UOPF\Facade\Database;
use UOPF\Exception\DuplicateUniqueColumnException;
use UOPF\Exception\DuplicateRelationshipException;
use UOPF\Exception\RelationshipNonexistenceException;

/**
 * Relationship Manager
 */
final class Relationship extends Manager {
    public function __construct(
        /**
         * The type of these relationships.
         */
        public readonly string $type
    ) {}

    public function getTableName(): string {
        return 'relationships';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function fetchDirectly(int $subject, int $object, ?DatabaseLockType $lock = null): ?Model {
        return $this->findEntryDirectly([
            'type' => $this->type,
            'subject' => $subject,
            'object' => $object
        ], $lock);
    }

    public function connect(int $subject, int $object): Model {
        try {
            return $this->createEntry([
                'type' => $this->type,
                'subject' => $subject,
                'object' => $object,
                'created' => Database::getCurrentTime()
            ]);
        } catch (DuplicateUniqueColumnException $exception) {
            throw new DuplicateRelationshipException('Relationship to connect already exists.', previous: $exception);
        }
    }

    public function disconnect(int $subject, int $object): Model {
        $conditions = [
            'type' => $this->type,
            'subject' => $subject,
            'object' => $object
        ];

        return Database::transaction(function () use (&$conditions) {
            if (!$locked = $this->findEntryDirectly($conditions, DatabaseLockType::write))
                throw new RelationshipNonexistenceException('Relationship to disconnect does not exist.');

            $this->deleteLockedEntry($locked);
            return $locked;
        });
    }
}
