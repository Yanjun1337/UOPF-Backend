<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class RecordContentValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: (1024 * 1024 * 4) - 1 // 4 MB.
        );
    }
}
