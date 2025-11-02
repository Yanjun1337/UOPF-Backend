<?php
declare(strict_types=1);
namespace UOPF;

use Medoo\Medoo;

/**
 * Database Manager
 */
final class Database extends Medoo {
    /**
     * The number of recursive transaction levels.
     */
    protected int $transactionNestingLevel = 0;

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
