<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class AuthenticationPassword extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Password Change Authentication';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent for two-factor authentication when users are trying to change their passwords.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'AuthenticationPasswordSubject',
            'AuthenticationPasswordBody'
        ];
    }
}
