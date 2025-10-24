<?php
declare(strict_types=1);
namespace UOPF;

/**
 * Trustless Data Validator
 */
abstract class Validator {
    /**
     * Returns the filtered value that satisfies the rules.
     */
    abstract public function filter(mixed $value): mixed;
}
