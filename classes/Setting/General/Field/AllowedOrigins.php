<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class AllowedOrigins extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'allowedOrigins';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Allowed Origins';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'URLs allowed to access the backend. One per line.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'textarea';

    /**
     * The default value of the setting field.
     */
    protected(set) mixed $default = '';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new StringValidator(
            max: 65536
        );
    }
}
