<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidationException;

class IntegerValidator extends Validator {
    public function __construct(
        public readonly ?int $max = null,
        public readonly ?int $min = null
    ) {}

    public function filter(mixed $value): int {
        if (!is_numeric($value))
            throw new ValidationException('Value cannot be converted to a number.');

        $value = $value + 0;

        if (!is_int($value))
            throw new ValidationException('Value must be an integer, not a decimal.');

        if (isset($this->max)) {
            if ($value > $this->max)
                throw new ValidationException("Value must be less than or equal to {$this->max}.");
        }

        if (isset($this->min)) {
            if ($value < $this->min)
                throw new ValidationException("Value must be more than or equal to {$this->min}.");
        }

        return $value;
    }
}
