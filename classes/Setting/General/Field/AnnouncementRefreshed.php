<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Field;

use UOPF\Validator;
use UOPF\Setting\Type\Metadata as MetadataField;
use UOPF\Validator\BooleanValidator;

final class AnnouncementRefreshed extends MetadataField {
    /**
     * The name of the setting field.
     */
    protected(set) string $name = 'announcement/refreshed';

    /**
     * The label of the setting field.
     */
    protected(set) string $label = 'Push Announcement';

    /**
     * The description of the setting field.
     */
    protected(set) string $description = 'Checking and saving will reopen the announcement for users who have opted out of receiving them.';

    /**
     * The type of the setting field.
     */
    protected(set) string $type = 'switch';

    /**
     * Returns the value of the field.
     */
    public function get(): false {
        return false;
    }

    /**
     * Sets the value of the field.
     */
    public function set(mixed $value): void {
        if ($value)
            parent::set(time());
    }

    /**
     * Returns the validator for the setting field.
     */
    public function getValidator(): Validator {
        return new BooleanValidator();
    }
}
