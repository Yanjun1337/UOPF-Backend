<?php
declare(strict_types=1);
namespace UOPF\Setting\StaticPage\Group;

use UOPF\Setting\Group;

final class PrivacyPolicy extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Privacy Policy';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Explains how this website stores and processes user data.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'PrivacyPolicyTitle',
            'PrivacyPolicyContent'
        ];
    }
}
