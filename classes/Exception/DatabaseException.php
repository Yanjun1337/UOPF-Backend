<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;
use PDOException;
use UOPF\Exception;

final class DatabaseException extends Exception {
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

    public static function createFromPDO(PDOException $exception): static {
        return new static(
            $exception->getMessage(),
            previous: $exception,
            databaseCode: $exception->getCode()
        );
    }
}
