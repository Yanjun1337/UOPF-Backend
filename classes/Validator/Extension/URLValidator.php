<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;
use UOPF\Exception\ValidationException;

final class URLValidator extends StringValidator {
    public function __construct(
        public readonly ?bool $hasTrailingSlash = null
    ) {
        parent::__construct(
            max: 1024,
            format: 'url'
        );
    }

    public function filter(mixed $value): string {
        $value = parent::filter($value);

        if (isset($this->hasTrailingSlash)) {
            if ($this->hasTrailingSlash) {
                if (substr($value, -1) !== '/')
                    throw new ValidationException('Value must end with a slash (/).');
            } else {
                if (substr($value, -1) === '/')
                    throw new ValidationException('Value cannot end with a slash (/).');
            }
        }

        return $value;
    }
}
