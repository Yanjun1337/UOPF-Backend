<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class AuthenticationUnregistration extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Unregistration Authentication';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent for two-factor authentication when users are trying to request to unregister their accounts.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'AuthenticationUnregistrationSubject',
            'AuthenticationUnregistrationBody'
        ];
    }
}
