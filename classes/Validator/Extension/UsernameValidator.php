<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class UsernameValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: 24,
            regex: '[a-zA-Z0-9_\-]*'
        );
    }
}
