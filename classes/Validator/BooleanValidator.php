<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidationException;

final class BooleanValidator extends Validator {
    public function filter(mixed $value): bool {
        if (is_bool($value))
            return $value;

        if (is_string($value)) {
            $lowercaseValue = strtolower($value);

            if (in_array($lowercaseValue, ['true', 'on', '1'], true))
                return true;

            if (in_array($lowercaseValue, ['false', 'off', '0'], true))
                return false;
        } elseif (is_int($value)) {
            if (in_array($value, [0, 1], true))
                return boolval($value);
        }

        throw new ValidationException('Value cannot be converted to a boolean.');
    }
}
