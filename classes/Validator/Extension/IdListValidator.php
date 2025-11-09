<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\ListValidator;
use UOPF\Validator\IntegerValidator;

final class IdListValidator extends ListValidator {
    public function __construct() {
        parent::__construct(
            separator: ',',
            trimSplitElement: true,
            allowDuplication: false,

            elementValidator: new IntegerValidator(
                min: 1
            )
        );
    }
}
