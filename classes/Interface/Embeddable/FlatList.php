<?php
declare(strict_types=1);
namespace UOPF\Interface\Embeddable;

use UOPF\Interface\Embeddable;

final class FlatList extends Embeddable {
    public function __construct(
        /**
         * The embeddable list.
         */
        public readonly array $value
    ) {}
}
