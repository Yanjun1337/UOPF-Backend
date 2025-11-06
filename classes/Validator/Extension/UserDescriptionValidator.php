<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;
use UOPF\Exception\ValidationException;

final class UserDescriptionValidator extends StringValidator {
    public function __construct() {
        parent::__construct(max: 1024);
    }

    public function filter(mixed $value): string {
        $value = parent::filter($value);

        if (mb_strlen($value) > 256)
            throw new ValidationException('Value is too long.');

        return $value;
    }
}
