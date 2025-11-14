<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class AuthenticationEmail extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Email Change Authentication';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent for two-factor authentication when users are trying to change their email addresses.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'AuthenticationEmailSubject',
            'AuthenticationEmailBody'
        ];
    }
}
