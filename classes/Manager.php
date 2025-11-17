<?php
declare(strict_types=1);
namespace UOPF;

use PDOException;
use UOPF\Facade\Cache;
use UOPF\Facade\Database;
use UOPF\Exception\DatabaseException;

/**
 * Data Table Manager
 */
abstract class Manager {
    /**
     * The list of indexes created for accessing the entries cache.
     */
    protected array $indexes = [];

    /**
     * The namespace under which entries cache is stored.
     */
    protected string $cacheNamespace {
        get {
            return $this->getTableName() . '/';
        }
    }

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

        $this->removeEntryFromCache($locked);
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
     * Finds an entry that matches specific conditions.
     */
    public function findEntry(array $conditions): ?Model {
        if ($cached = $this->findEntryFromCache($conditions))
            return $cached;

        return Database::transaction(function () use (&$conditions) {
            if ($entry = $this->findEntryDirectly($conditions, DatabaseLockType::read)) {
                $this->cacheEntry($entry);
                return $entry;
            } else {
                return null;
            }
        });
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
        return $this->findEntry([$field => $value]);
    }

    /**
     * Queries entries from the database.
     */
    public function queryEntries(array $where): RetrievedEntries {
        $columns = array_keys($this->getModelClass()::getSchema());
        $data = Database::select($this->getTableName(), $columns, $where);
        $entries = array_map([$this, 'initializeEntry'], $data);

        if (isset($where['TOTAL']) && $where['TOTAL'])
            return new RetrievedEntries($entries, Database::fetchTotalRows());
        else
            return new RetrievedEntries($entries);
    }

    /**
     * Queries entries from the database using arbitrary SQL.
     */
    public function queryEntriesArbitrarily(string $sql, array $parameters): RetrievedEntries {
        $statement = Database::getProperty('pdo')->prepare($sql);
        $statement->execute($parameters);

        $data = array_values($statement->fetchAll());
        $entries = array_map([$this, 'initializeEntry'], $data);

        if (strpos(trim($sql), 'SELECT SQL_CALC_FOUND_ROWS ') === 0)
            return new RetrievedEntries($entries, Database::fetchTotalRows());
        else
            return new RetrievedEntries($entries);
    }

    /**
     * Increments a field of a locked entry.
     */
    public function incrementLockedEntryField(Model $locked, string $field, int $step = 1): void {
        $this->updateLockedEntry($locked, [$field => $locked[$field] + $step]);
    }

    /**
     * Finds an entry from the cache engine that matches specific conditions.
     */
    protected function findEntryFromCache(array $conditions): ?Model {
        if (isset($conditions['id']) && count($conditions) === 1)
            return Cache::get($this->cacheNamespace . $conditions['id']);

        if ($identifier = $this->findEntryIdentifierByIndexes($conditions))
            return Cache::get($this->cacheNamespace . $identifier);

        return null;
    }

    /**
     * Stores an entry in the cache engine.
     */
    protected function cacheEntry(Model $entry): void {
        foreach ($this->indexes as $name => $fields)
            $this->setEntryCacheIndex($entry, $name, $fields);

        Cache::set($this->cacheNamespace . $entry['id'], $entry);
    }

    /**
     * Removes the cache of an entry from the cache engine.
     */
    protected function removeEntryFromCache(Model $entry): void {
        foreach ($this->indexes as $name => $fields)
            $this->removeEntryCacheIndex($entry, $name, $fields);

        Cache::remove($this->cacheNamespace . $entry['id'], $entry);
    }

    /**
     * Finds the identifier of an entry from the cache engine that matches specific conditions.
     */
    protected function findEntryIdentifierByIndexes(array $conditions): ?int {
        ksort($conditions);
        $keys = array_keys($conditions);

        foreach ($this->indexes as $key => $fields) {
            sort($fields);

            if ($fields === $keys) {
                $name = $key;
                break;
            }
        }

        if (!isset($name))
            return null;

        $namespace = "{$this->cacheNamespace}{$name}/";
        return Cache::get($namespace . implode('/', $conditions));
    }

    /**
     * Sets an index for an entry in the cache engine.
     */
    protected function setEntryCacheIndex(Model $entry, string $name, array $fields): void {
        asort($fields);
        $values = [];

        foreach ($fields as $field) {
            if (isset($entry[$field])) {
                if (!is_scalar($entry[$field]))
                    throw new Exception('Only scalar fields can be used in a cache index.');

                $values[] = $entry[$field];
            } else {
                $values[] = '';
            }
        }

        if (empty($values))
            return;

        $namespace = "{$this->cacheNamespace}{$name}/";
        Cache::set($namespace . implode('/', $values), $entry['id']);
    }

    /**
     * Removes an index for an entry from the cache engine.
     */
    protected function removeEntryCacheIndex(Model $entry, string $name, array $fields): void {
        asort($fields);
        $values = [];

        foreach ($fields as $field) {
            if (isset($entry[$field])) {
                if (!is_scalar($entry[$field]))
                    return;

                $values[] = $entry[$field];
            } else {
                $values[] = '';
            }
        }

        $namespace = "{$this->cacheNamespace}{$name}/";
        Cache::remove($namespace . implode('/', $values));
    }

    /**
     * Check whether the nonexistence mark of an entry exists in the cache engine.
     */
    protected function hasEntryNonexistenceCache(array $conditions): bool {
        return boolval(Cache::get($this->getEntryNonexistenceCacheKey($conditions)));
    }

    /**
     * Sets the nonexistence mark of an entry in the cache engine.
     */
    protected function cacheEntryNonexistence(array $conditions): void {
        Cache::set($this->getEntryNonexistenceCacheKey($conditions), true);
    }

    /**
     * Removes the nonexistence mark of an entry form the cache engine.
     */
    protected function removeEntryNonexistenceFromCache(array $conditions): void {
        Cache::remove($this->getEntryNonexistenceCacheKey($conditions));
    }

    /**
     * Returns the key of the nonexistence mark of an entry in the cache engine.
     */
    protected function getEntryNonexistenceCacheKey(array $conditions): string {
        ksort($conditions);
        return $this->cacheNamespace . 'nonexistence/' . implode('/', $conditions);
    }
}
