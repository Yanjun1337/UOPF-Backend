<?php
declare(strict_types=1);
namespace UOPF;

use PDOStatement;
use Medoo\Medoo;

/**
 * Database Manager
 */
final class Database extends Medoo {
    /**
     * The stack of select flags.
     */
    protected array $selectFlagsStack = [];

    /**
     * The number of recursive transaction levels.
     */
    protected int $transactionNestingLevel = 0;

    public function getCurrentTime(): string {
        return gmdate('Y-m-d H:i:s');
    }

    public function select(string $table, mixed $join, mixed $columns = null, mixed $where = null): ?array {
        if (isset($where))
            $handle = &$where;
        else
            $handle = &$columns;

        $flags = [];

        if (isset($handle['TOTAL'])) {
            if ($handle['TOTAL'])
                $flags[] = 'total';

            unset($handle['TOTAL']);
        }

        if (isset($handle['LOCK'])) {
            switch ($handle['LOCK']) {
                case DatabaseLockType::read:
                    $flags[] = 'readLock';
                    break;

                case DatabaseLockType::write:
                    $flags[] = 'writeLock';
                    break;

                default:
                    throw new Exception('Unsupported lock type.');
            }

            unset($handle['LOCK']);
        }

        $this->selectFlagsStack[] = $flags;
        return parent::select(...func_get_args());
    }

    public function exec(string $statement, array $map = [], ?callable $callback = null): ?PDOStatement {
        $flags = array_pop($this->selectFlagsStack) ?? [];

        if (in_array('total', $flags, true))
            $statement = preg_replace('/^SELECT\s/i', 'SELECT SQL_CALC_FOUND_ROWS ', $statement);

        if (in_array('readLock', $flags, true))
            $statement .= ' LOCK IN SHARE MODE';
        elseif (in_array('writeLock', $flags, true))
            $statement .= ' FOR UPDATE';

        $arguments = func_get_args();
        $arguments[0] = $statement;

        return parent::exec(...$arguments);
    }

    public function fetchTotalRows(): int {
        $statement = $this->query('SELECT FOUND_ROWS()');
        return intval($statement->fetchAll()[0][0]);
    }

    public function getPagingLimit(int $perPage, int $page = 1): int|array {
        if ($page <= 1)
            return $perPage;

        $offset = ($page - 1) * $perPage;
        return [$offset, $perPage];
    }

    public function getSearchClause(array $keywords, array $fields): array {
        $clause = [];

        foreach ($keywords as $index => $keyword) {
            $keywordClause = [];
            $escapedKeyword = addcslashes($keyword, '_%\\');

            foreach ($fields as $field)
                $keywordClause["{$field}[~]"] = "%{$escapedKeyword}%";

            $clause["OR #{$index}"] = $keywordClause;
        }

        return $clause;
    }

    public function transaction(callable $callback): mixed {
        if ($this->transactionNestingLevel === 0) {
            $this->pdo->beginTransaction();
        } else {
            $identifier = $this->getTransactionSavepointIdentifier();
            $this->exec("SAVEPOINT `{$identifier}`");
        }

        ++$this->transactionNestingLevel;

        try {
            $return = $callback();

            if ($this->transactionNestingLevel > 1) {
                $identifier = $this->getTransactionSavepointIdentifier(-1);
                $this->exec("RELEASE SAVEPOINT `{$identifier}`");
            } else {
                $this->pdo->commit();
            }

            return $return;
        } catch (\Exception $exception) {
            if ($this->transactionNestingLevel > 1) {
                $identifier = $this->getTransactionSavepointIdentifier(-1);
                $this->exec("ROLLBACK TO SAVEPOINT `{$identifier}`");
            } else {
                $this->pdo->rollBack();
            }

            throw $exception;
        } finally {
            --$this->transactionNestingLevel;
        }
    }

    protected function getTransactionSavepointIdentifier(int $offset = 0): string {
        return 'LEVEL_' . ($this->transactionNestingLevel + $offset);
    }
}
