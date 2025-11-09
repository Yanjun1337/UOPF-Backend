<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidationException;

class ListValidator extends Validator {
    public function __construct(
        public readonly ?string $separator = null,
        public readonly ?bool $trimSplitElement = null,
        public readonly ?bool $allowDuplication = null,
        public readonly ?Validator $elementValidator = null
    ) {}

    public function filter(mixed $value): array {
        if (is_string($value)) {
            if (isset($this->separator)) {
                $value = explode($this->separator, $value);

                if (isset($this->trimSplitElement)) {
                    if ($this->trimSplitElement)
                        $value = array_map('trim', $value);
                }
            } else {
                throw new ValidationException('Value must be a list.');
            }
        } elseif (!is_array($value)) {
            throw new ValidationException('Value must be a list.');
        }

        if (isset($this->elementValidator)) {
            try {
                foreach ($value as &$elementValue)
                    $elementValue = $this->elementValidator->filter($elementValue);
            } catch (ValidationException $exception) {
                $message = $exception->getMessage();
                throw new ValidationException("Element in list: {$message}", previous: $exception);
            }
        }

        if (isset($this->allowDuplication)) {
            if (!$this->allowDuplication) {
                if (count($value) !== count(array_unique($value)))
                    throw new ValidationException('Value cannot contain duplicate elements.');
            }
        }

        return $value;
    }
}
