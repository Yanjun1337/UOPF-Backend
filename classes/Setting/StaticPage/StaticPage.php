<?php
declare(strict_types=1);
namespace UOPF\Setting\StaticPage;

use UOPF\Setting\Page;

final class StaticPage extends Page {
    /**
     * The name of the setting page.
     */
    protected(set) string $name = 'pages';

    /**
     * The title of the setting page.
     */
    protected(set) string $title = 'Static Pages';

    /**
     * The label of the setting page in the menu.
     */
    protected(set) string $menuLabel = 'Pages';

    /**
     * The description of the setting page.
     */
    protected(set) string $description = 'Edit static pages of this website.';

    /**
     * Returns the classes of the registered setting groups.
     */
    public function getGroups(): array {
        return [
            'PrivacyPolicy',
            'Contact',
            'TermsAndConditions'
        ];
    }
}
