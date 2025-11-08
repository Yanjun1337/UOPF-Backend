<?php
declare(strict_types=1);
namespace UOPF;

final class RetrievedEntries {
    public function __construct(
        /**
         * Entries retrieved from the database.
         */
        public readonly array $entries,

        /**
         * Total number of entries before pagination.
         */
        public readonly ?int $total = null
    ) {}
}
