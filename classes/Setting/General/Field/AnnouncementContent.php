<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\StringValidator;

final class AnnouncementContent extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'announcement/content';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Content';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'The content of the announcement in Markdown format.';

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
