<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\Extension\URLValidator;

final class Instagram extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'social/instagram';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Instagram';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'text';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new URLValidator();
    }
}
