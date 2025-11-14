<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class Title extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'title';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Website Title';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The website title displayed on the browser tab.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'text';

    /**
     * The default value of the setting field.
     */
    protected(set) mixed $default = 'UOPF';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new StringValidator(
            allowEmpty: false,
            max: 64
        );
    }
}
