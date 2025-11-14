<?php
declare(strict_types=1);
namespace UOPF\Setting\Type;

use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Setting\Field;
use UOPF\Exception\DuplicateUniqueColumnException;

/**
 * Metadata Setting Field
 */
abstract class Metadata extends Field {
    /**
     * Returns the value of the field.
     */
    public function get(): mixed {
        return SystemMetadataManager::get($this->name);
    }

    /**
     * Sets the value of the field.
     */
    public function set(mixed $value): void {
        SystemMetadataManager::set($this->name, $value);
    }

    /**
     * Fill the default value of the setting field.
     */
    public function fillDefault(): void {
        if (!isset($this->default))
            return;

        try {
            SystemMetadataManager::add($this->name, $this->default);
        } catch (DuplicateUniqueColumnException) {}
    }
}
