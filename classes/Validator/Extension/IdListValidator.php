<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\ListValidator;
use UOPF\Validator\IntegerValidator;

final class IdListValidator extends ListValidator {
    public function __construct(
        ?int $maximum = null,
        ?bool $allowEmpty = null
    ) {
        parent::__construct(
            separator: ',',
            trimSplitElement: true,
            allowDuplication: false,
            min: isset($allowEmpty) && !$allowEmpty ? 1 : null,
            max: $maximum,

            elementValidator: new IntegerValidator(
                min: 1
            )
        );
    }
}
