<?php
declare(strict_types=1);
namespace UOPF;

use PDOException;
use UOPF\Facade\Database;
use UOPF\Exception\DatabaseException;

/**
 * Data Table Manager
 */
abstract class Manager {
    /**
     * Returns the name of the table used in the database.
     */
    abstract public function getTableName(): string;

    /**
     * Returns the class name of the entry model.
     */
    abstract public function getModelClass(): string;

    /**
     * Initializes an entry model.
     */
    public function initializeEntry(array $row): Model {
        $class = $this->getModelClass();
        return new $class($row);
    }

    /**
     * Inserts a new entry into the database and returns the ID.
     */
    public function insertEntry(array $data): int {
        try {
            $statement = Database::insert($this->getTableName(), $data);
        } catch (PDOException $exception) {
            throw DatabaseException::createFromPDOException($exception);
        }

        if ($statement->rowCount() !== 1)
            throw new Exception('Failed to insert the entry into the database.');

        return intval(Database::id());
    }

    /**
     * Creates a new entry, inserts it into the database, and returns the model.
     */
    public function createEntry(array $data): Model {
        return Database::transaction(function () use ($data) {
            $id = $this->insertEntry($data);
            $conditions = [static::getModelClass()::getIdentifierField() => $id];

            if ($entry = $this->findEntryDirectly($conditions))
                return $entry;
            else
                throw new Exception('Failed to fetch the created entry.');
        });
    }

    /**
     * Updates an entry using its ID and returns the updated model.
     */
    public function updateEntry(int $id, array $data): Model {
        return Database::transaction(function () use (&$id, &$data) {
            if (!$locked = $this->fetchEntryDirectly($id, lock: DatabaseLockType::write))
                throw new Exception('Failed to fetch the entry to update.');

            $this->updateLockedEntry($locked, $data);
            return $this->fetchEntryDirectly($id);
        });
    }

    /**
     * Updates a locked entry.
     */
    public function updateLockedEntry(Model $locked, array $data): void {
        $identifierField = static::getModelClass()::getIdentifierField();
        $conditions = [$identifierField => $locked[$identifierField]];

        try {
            Database::update(static::getTableName(), $data, $conditions);
        } catch (PDOException $exception) {
            throw DatabaseException::createFromPDOException($exception);
        }
    }

    /**
     * Deletes a locked entry.
     */
    public function deleteLockedEntry(Model $locked): void {
        try {
            $statement = Database::delete(static::getTableName(), ['id' => $locked['id']]);
        } catch (PDOException $exception) {
            throw DatabaseException::createFromPDOException($exception);
        }

        if ($statement->rowCount() !== 1)
            throw new Exception('Failed to delete entry.');
    }

    /**
     * Finds an entry directly from the database that matches specific conditions.
     */
    public function findEntryDirectly(array $conditions, ?DatabaseLockType $lock = null): ?Model {
        $where = [
            'AND' => $conditions,
            'LIMIT' => 1
        ];

        if (isset($lock))
            $where['LOCK'] = $lock;

        $columns = array_keys($this->getModelClass()::getSchema());
        $data = Database::select($this->getTableName(), $columns, $where);

        if (isset($data[0]))
            return $this->initializeEntry($data[0]);
        else
            return null;
    }

    /**
     * Fetches an entry directly from the database using a specific field.
     */
    public function fetchEntryDirectly(string|int|float|bool $value, string $field = 'id', ?DatabaseLockType $lock = null): ?Model {
        return $this->findEntryDirectly([$field => $value], $lock);
    }

    /**
     * Fetches an entry using a specific field.
     */
    public function fetchEntry(string|int|float|bool $value, string $field = 'id'): ?Model {
        return $this->fetchEntryDirectly($value, $field); // @TODO
    }

    /**
     * Increments a field of a locked entry.
     */
    public function incrementLockedEntryField(Model $locked, string $field, int $step = 1): void {
        $this->updateLockedEntry($locked, [$field => $locked[$field] + $step]);
    }
}
