<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class AuthenticationEmailSubject extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'mail/auth/email/subject';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Mail Subject';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The single-line subject of this type of email.';

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
