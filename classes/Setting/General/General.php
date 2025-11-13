<?php
declare(strict_types=1);
namespace UOPF\Setting\General;

use UOPF\Setting\Page;

final class General extends Page {
    /**
     * The name of the setting page.
     */
    protected(set) string $name = 'general';

    /**
     * The title of the setting page.
     */
    protected(set) string $title = 'Basic Settings';

    /**
     * The label of the setting page in the menu.
     */
    protected(set) string $menuLabel = 'General';

    /**
     * The description of the setting page.
     */
    protected(set) string $description = 'Basic settings for this website.';

    /**
     * Returns the classes of the registered setting groups.
     */
    public function getGroups(): array {
        return [
            'Title',
            'Addresses',
            'SocialMedia'
        ];
    }
}
