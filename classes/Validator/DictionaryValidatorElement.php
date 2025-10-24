<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;

final class DictionaryValidatorElement {
    public function __construct(
        public readonly ?string $label = null,
        public readonly ?bool $required = null,
        public readonly mixed $default = null,
        public readonly ?Validator $validator = null
    ) {}

    public function isRequired(): bool {
        return isset($this->required) && $this->required;
    }
}
