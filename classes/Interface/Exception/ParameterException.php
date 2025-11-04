<?php
declare(strict_types=1);
namespace UOPF\Interface\Exception;

use Throwable;
use UOPF\Interface\Exception;

/**
 * API Parameter Exception
 */
final class ParameterException extends Exception {
    public function __construct(
        string $message = '',
        ?string $field = null,
        array $data = [],
        ?Throwable $previous = null
    ) {
        if (isset($field))
            $data['field'] = $field;

        parent::__construct($message, 400, $data, $previous);
    }
}
