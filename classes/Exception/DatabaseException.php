<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;
use PDOException;
use UOPF\Exception;

class DatabaseException extends Exception {
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,

        /**
         * Database error code.
         */
        public readonly ?string $databaseCode = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function createFromPDOException(PDOException $exception): static {
        $duplicateColumn = static::extractDuplicateColumnFromPDOException($exception);

        if (isset($duplicateColumn)) {
            return new DuplicateUniqueColumnException(
                $duplicateColumn,
                $exception->getMessage(),
                previous: $exception
            );
        } else {
            return new self(
                $exception->getMessage(),
                previous: $exception,
                databaseCode: $exception->getCode()
            );
        }
    }

    protected static function extractDuplicateColumnFromPDOException(PDOException $exception): ?string {
        if ($exception->errorInfo[0] != 23000)
            return null;

        if ($exception->errorInfo[1] != 1062)
            return null;

        $message = $exception->errorInfo[2];
        $message = substr($message, 0, -1);

        if (($position = strrpos($message, "'")) === false)
            return null;

        return substr($message, $position + 1);
    }
}
