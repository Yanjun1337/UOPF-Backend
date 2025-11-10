<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\IntegerValidator;

final class NumberPerPageValidator extends IntegerValidator {
    public function __construct(?int $maximum = 100) {
        parent::__construct(
            min: 1,
            max: $maximum
        );
    }
}
