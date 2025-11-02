<?php
declare(strict_types=1);
namespace UOPF;

use ArrayAccess;

/**
 * Entry Data Model
 */
abstract class Model implements ArrayAccess {
    /**
     * Data.
     */
    public readonly array $data;

    /**
     * Constructor.
     */
    public function __construct(array $row) {
        $data = [];

        foreach (static::getSchema() as $name => $type) {
            if (array_key_exists($name, $row))
                $data[$name] = static::resolveField($row[$name], $type);
            else
                throw new Exception('Missing model field.');
        }

        if (!isset($data[static::getIdentifierField()]))
            throw new Exception('Missing identifier field within model.');

        $this->data = $data;
    }

    /**
     * Checks whether an offset exists.
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->data[$offset]);
    }

    /**
     * Returns the value at specified offset.
     */
    public function offsetGet(mixed $offset): mixed {
        return $this->data[$offset] ?? null;
    }

    /**
     * Assigns a value to the specified offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        throw new Exception('Field of the model is readonly.');
    }

    /**
     * Unsets an offset.
     */
    public function offsetUnset(mixed $offset): void {
        throw new Exception('Field of the model is readonly.');
    }

    /**
     * Returns the schema of the model.
     */
    abstract public static function getSchema(): array;

    /**
     * Returns the name of the identifier field.
     */
    public static function getIdentifierField(): string {
        return 'id';
    }

    /**
     * Resolves a field according to the schema.
     */
    protected static function resolveField(mixed $value, ModelFieldType $type): mixed {
        switch ($type) {
            case ModelFieldType::string:
                return strval($value);

            case ModelFieldType::integer:
                return intval($value);

            case ModelFieldType::float:
                return floatval($value);

            case ModelFieldType::boolean:
                return boolval($value);

            case ModelFieldType::serialized:
                return unserialize($value);

            case ModelFieldType::date:
                return strtotime("{$value} GMT");

            default:
                throw new Exception('Unrecognized field type.');
        }
    }
}
