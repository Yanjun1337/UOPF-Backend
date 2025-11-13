<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class RegistrationBody extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'mail/registration/body';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Mail Body';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The body of this type of email in Markdown format.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'textarea';

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new StringValidator(
            max: 65536
        );
    }
}
