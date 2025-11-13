<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\Extension\URLValidator;

/**
 * Setting Field
 */
final class FrontendAddress extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'frontendAddress';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Frontend URL';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The URL must end with a slash <code>/</code>.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'text';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new URLValidator(
            hasTrailingSlash: true
        );
    }
}
