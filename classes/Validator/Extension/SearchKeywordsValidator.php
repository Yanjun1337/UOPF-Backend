<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator;
use UOPF\Validator\StringValidator;
use UOPF\Exception\ValidationException;

final class SearchKeywordsValidator extends Validator {
    public function filter(mixed $value): array {
        $value = (new StringValidator(
            allowEmpty: false,
            max: 1024
        ))->filter($value);

        $value = preg_split('/\s/', $value);
        $value = array_map('trim', $value);
        $value = array_values(array_unique($value));
        $value = array_filter($value, [static::class, 'isNotEmpty']);

        if (count($value) > 8)
            throw new ValidationException('Value contains too many words.');
        else
            return $value;
    }

    protected static function isNotEmpty(string $text): bool {
        return strlen($text) > 0;
    }
}
