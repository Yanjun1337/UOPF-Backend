<?php
declare(strict_types=1);
namespace UOPF\Setting\StaticPage\Group;

use UOPF\Setting\Group;

final class TermsAndConditions extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Terms and Conditions';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Describes what users should know before registering.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'TermsAndConditionsTitle',
            'TermsAndConditionsContent'
        ];
    }
}
