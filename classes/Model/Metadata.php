<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\Exception;
use UOPF\MetadataType;
use UOPF\ModelFieldType;

final class Metadata extends Model {
    /**
     * Decoded value.
     */
    protected readonly mixed $decodedValue;

    public function getDecodedValue(): mixed {
        if (!isset($this->decodedValue)) {
            $type = MetadataType::from($this->data['type']);
            $this->decodedValue = static::decodeValue($this->data['value'], $type);
        }

        return $this->decodedValue;
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'group' => ModelFieldType::string,
            'affiliated_to' => ModelFieldType::integer,
            'name' => ModelFieldType::string,
            'value' => ModelFieldType::string,
            'type' => ModelFieldType::string
        ];
    }

    protected static function decodeValue(mixed $value, MetadataType $type): mixed {
        switch ($type) {
            case MetadataType::string:
                return strval($value);

            case MetadataType::integer:
                return intval($value);

            case MetadataType::float:
                return floatval($value);

            case MetadataType::boolean:
                return boolval($value);

            case MetadataType::serialized:
                return unserialize($value);

            default:
                throw new Exception('Unrecognized metadata type.');
        }
    }
}
