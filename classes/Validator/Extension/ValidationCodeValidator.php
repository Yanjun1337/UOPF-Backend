<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class ValidationCodeValidator extends StringValidator {
    public function __construct() {
        parent::__construct(regex: '\d{6}');
    }
}
