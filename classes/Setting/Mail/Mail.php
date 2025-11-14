<?php
declare(strict_types=1);
namespace UOPF\Setting\Mail;

use UOPF\Setting\Page;

final class Mail extends Page {
    /**
     * The name of the setting page.
     */
    protected(set) string $name = 'mail';

    /**
     * The title of the setting page.
     */
    protected(set) string $title = 'Mail Templates';

    /**
     * The label of the setting page in the menu.
     */
    protected(set) string $menuLabel = 'Mail';

    /**
     * The description of the setting page.
     */
    protected(set) string $description = 'Templates for various types of emails that contain a verification code. The shortcode <code>{code}</code>, which will be replaced with the actual verification code, should be inserted into these templates.';

    /**
     * Returns the classes of the registered setting groups.
     */
    public function getGroups(): array {
        return [
            'Registration',
            'PasswordReset',
            'AuthenticationPassword',
            'AuthenticationEmail',
            'UpdateEmail'
        ];
    }
}
