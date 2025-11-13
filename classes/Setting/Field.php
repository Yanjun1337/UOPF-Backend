<?php
declare(strict_types=1);
namespace UOPF\Setting;

use UOPF\Validator;

/**
 * Setting Field
 */
abstract class Field {
    /**
     * The name of the setting field.
     */
    protected(set) string $name;

    /**
     * The label of the setting field.
     */
    protected(set) string $label;

    /**
     * The description of the setting field.
     */
    protected(set) string $description;

    /**
     * The type of the setting field.
     */
    protected(set) string $type;

    /**
     * Returns the validator for the setting field.
     */
    abstract public function getValidator(): Validator;

    /**
     * Returns the value of the setting field.
     */
    abstract public function get(): mixed;

    /**
     * Sets the value of the setting field.
     */
    abstract public function set(mixed $value): void;

    /**
     * Returns the schema of the setting field.
     */
    public function getSchema(): array {
        $schema = [
            'label' => $this->label,
            'type' => $this->type,
            'value' => $this->get()
        ];

        if (isset($this->description))
            $schema['description'] = $this->description;

        return $schema;
    }
}
