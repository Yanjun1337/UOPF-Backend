<?php
declare(strict_types=1);
namespace UOPF\Validator;

use UOPF\Validator;
use UOPF\Exception\ValidatorException;
use UOPF\Exception\ValidationException;
use UOPF\Exception\DictionaryValidationException;

final class DictionaryValidator extends Validator {
    public function __construct(
        public readonly array $elements
    ) {}

    public function filter(mixed $value): array {
        if (!is_array($value))
            throw new ValidationException('Value must be a dictionary.');

        $filtered = [];

        foreach ($this->elements as $key => $element) {
            if (!($element instanceof DictionaryValidatorElement))
                throw new ValidatorException('Invalid dictionary element.');

            if (isset($element->label))
                $label = $element->label;
            else
                $label = strval($key);

            if (array_key_exists($key, $value)) {
                $elementValue = $value[$key];
            } else {
                if ($element->isRequired())
                    throw new DictionaryValidationException($key, $label, 'Value must be provided.');

                if (isset($element->default))
                    $elementValue = $element->default;
                else
                    continue;
            }

            if (isset($element->validator)) {
                try {
                    $elementValue = $element->validator->filter($elementValue);
                } catch (ValidationException $exception) {
                    throw new DictionaryValidationException($key, $label, $exception->getMessage(), $exception->getCode(), $exception);
                }
            }

            $filtered[$key] = $elementValue;
        }

        return $filtered;
    }
}
