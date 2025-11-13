<?php
declare(strict_types=1);
namespace UOPF\Setting\StaticPage\Group;

use UOPF\Setting\Group;

final class Contact extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Contact Information';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Lists our contact information.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'ContactTitle',
            'ContactContent'
        ];
    }
}
