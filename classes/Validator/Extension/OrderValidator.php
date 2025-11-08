<?php
declare(strict_types=1);
namespace UOPF\Validator\Extension;

use UOPF\Validator\EnumerationValidator;

final class OrderValidator extends EnumerationValidator {
    public const ASCENDING = 'ASC';
    public const DESCENDING = 'DESC';

    public function __construct() {
        parent::__construct([
            static::ASCENDING,
            static::DESCENDING
        ]);
    }
}
