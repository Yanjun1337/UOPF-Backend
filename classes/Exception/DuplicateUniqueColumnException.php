<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;

class DuplicateUniqueColumnException extends DatabaseException {
    public function __construct(
        /**
         * The name of the unique column that contains a duplicate value.
         */
        public readonly string $column,

        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,

        /**
         * Database error code.
         */
        public readonly ?string $databaseCode = null
    ) {
        parent::__construct($message, $code, $previous, $databaseCode);
    }
}
