<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class RecordTitleValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: pow(2, 16) - 1
        );
    }
}
