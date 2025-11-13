<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail\Group;

use UOPF\Setting\Group;

final class Registration extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Registration';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Sent to verify the authenticity of users’ email addresses during registration.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'RegistrationSubject',
            'RegistrationBody'
        ];
    }
}
