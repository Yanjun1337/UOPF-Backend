<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidationException;

final class EnumerationValidator extends Validator {
    public function __construct(
        public readonly array $values
    ) {}

    public function filter(mixed $value): mixed {
        if (in_array($value, $this->values, true))
            return $value;
        else
            throw new ValidationException('Unexpected value.');
    }
}
