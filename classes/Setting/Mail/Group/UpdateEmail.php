<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class UpdateEmail extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Update Email';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent to verify the authenticity of the new email addresses that users want to switch to.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'UpdateEmailSubject',
            'UpdateEmailBody'
        ];
    }
}
