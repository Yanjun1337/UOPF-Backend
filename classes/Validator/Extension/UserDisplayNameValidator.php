<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;
use UOPF\Exception\ValidationException;

final class UserDisplayNameValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: 128
        );
    }

    public function filter(mixed $value): string {
        $value = parent::filter($value);

        if (preg_match('/[\r\n\t\f\v]/', $value) !== 0)
            throw new ValidationException('Value cannot contain invisible characters except spaces.');

        if (trim($value) !== $value)
            throw new ValidationException('Value cannot contain spaces at the beginning or end.');

        if (mb_strlen($value) > 32)
            throw new ValidationException('Value is too long.');

        foreach (static::getDisallowedCharacters() as $character)
            if (strpos($value, $character) !== false)
                throw new ValidationException('Value contains reserved characters.');

        return $value;
    }

    protected static function getDisallowedCharacters(): array {
        return [
            '#',
            '@'
        ];
    }
}
