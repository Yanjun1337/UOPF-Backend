<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class ReasonValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: pow( 2, 16 ) - 1
        );
    }
}
