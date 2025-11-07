<?php
declare(strict_types=1);
namespace UOPF\Exception;

use Throwable;
use UOPF\Exception;

final class EnvironmentVariableException extends Exception {
    public function __construct(
        string $message = '',

        /**
         * The name of the environment variable related to the exception.
         */
        public readonly ?string $name = null,

        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
