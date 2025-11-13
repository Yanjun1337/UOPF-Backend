<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class PasswordReset extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Password Reset';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent to reset the passwords of users who have forgotten them.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'PasswordResetSubject',
            'PasswordResetBody'
        ];
    }
}
