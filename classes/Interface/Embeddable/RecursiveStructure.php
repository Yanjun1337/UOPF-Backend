<?php
declare(strict_types=1);
namespace UOPF\Interface\Embeddable;

use Closure;
use UOPF\Interface\Embeddable;

final class RecursiveStructure extends Embeddable {
    /**
     * The getter used to fetch a child structure.
     */
    public readonly Closure $getter;

    /**
     * Constructor
     */
    public function __construct(
        /**
         * The value used to fetch the structure.
         */
        public readonly string|int|float|bool $value,

        /**
         * The name of the field of the value.
         */
        public readonly string $field,

        callable $getter
    ) {
        $this->getter = Closure::fromCallable($getter);
    }

    /**
     * Returns embedded structure.
     */
    public function getStructure(): array|object {
        $getter = $this->getter;
        return $getter($this->value);
    }
}
