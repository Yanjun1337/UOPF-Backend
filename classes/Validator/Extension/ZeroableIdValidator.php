<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\IntegerValidator;

final class ZeroableIdValidator extends IntegerValidator {
    public function __construct() {
        parent::__construct(min: 0);
    }
}
