<?php
declare(strict_types=1);
namespace UOPF\Setting\StaticPage\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class TermsAndConditionsTitle extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'page/termsAndConditions/title';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Title';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The single-line title of this page.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'text';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new StringValidator(
            allowEmpty: false,
            max: 4096
        );
    }
}
