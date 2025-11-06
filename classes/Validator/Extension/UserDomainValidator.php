<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\StringValidator;

final class UserDomainValidator extends StringValidator {
    public function __construct() {
        parent::__construct(
            allowEmpty: false,
            max: 64,
            regex: '[a-z0-9\-_]*'
        );
    }
}
