<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\MetadataType;
use UOPF\DatabaseLockType;
use UOPF\Model\Metadata as Model;
use UOPF\Facade\Database;

/**
 * Metadata Manager
 */
final class Metadata extends Manager {
    public function __construct(
        /**
         * The group to which these metadata are affiliated.
         */
        public readonly string $group
    ) {}

    public function getTableName(): string {
        return 'metadata';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function get(string $name, ?int $affiliatedTo = null): mixed {
        $conditions = $this->getFindingConditions($name, $affiliatedTo);
        $entry = $this->findEntryDirectly($conditions);

        if ($entry)
            return $entry->getDecodedValue();
        else
            return null;
    }

    public function add(string $name, mixed $value, ?int $affiliatedTo = null): void {
        $this->insertEntry([
            'group' => $this->group,
            'affiliated_to' => $affiliatedTo,
            'name' => $name,
            'value' => static::encodeValue($value),
            'type' => static::determineValueType($value)->value
        ]);
    }

    public function set(string $name, mixed $value, ?int $affiliatedTo = null): void {
        Database::transaction(function () use (&$name, &$value, &$affiliatedTo) {
            $conditions = $this->getFindingConditions($name, $affiliatedTo);
            $locked = $this->findEntryDirectly($conditions, DatabaseLockType::write);

            if ($locked) {
                $this->updateLockedEntry($locked, [
                    'value' => static::encodeValue($value),
                    'type' => static::determineValueType($value)->value
                ]);
            } else {
                $this->add($name, $value, $affiliatedTo);
            }
        });
    }

    public function setLocked(Model $locked, mixed $value): void {
        $this->updateLockedEntry($locked, [
            'value' => static::encodeValue($value),
            'type' => static::determineValueType($value)->value
        ]);
    }

    public function fetchDirectly(string $name, ?int $affiliatedTo = null, ?DatabaseLockType $lock = null): ?Model {
        $conditions = $this->getFindingConditions($name, $affiliatedTo);
        return $this->findEntryDirectly($conditions, $lock);
    }

    protected function getFindingConditions(string $name, ?int $affiliatedTo = null): array {
        return [
            'group' => $this->group,
            'affiliated_to' => $affiliatedTo,
            'name' => $name
        ];
    }

    protected static function determineValueType(mixed $value): MetadataType {
        switch (true) {
            case is_string($value):
                return MetadataType::string;

            case is_integer($value):
                return MetadataType::integer;

            case is_float($value):
                return MetadataType::float;

            case is_bool($value):
                return MetadataType::boolean;

            default:
                return MetadataType::serialized;
        }
    }

    protected static function encodeValue(mixed $value): mixed {
        return is_scalar($value) ? $value : serialize($value);
    }
}
