<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Model;

/**
 * API Entry with Additional Fields
 */
abstract class EntryWith {
    public function __construct(
        /**
         * The entry.
         */
        public readonly Model $entry
    ) {}

    public static function createList(array $entries): array {
        $results = [];

        foreach ($entries as $key => $value)
            $results[$key] = new static($value);

        return $results;
    }
}
