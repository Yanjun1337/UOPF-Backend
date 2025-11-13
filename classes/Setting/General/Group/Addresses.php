<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Group;

use UOPF\Setting\Group;

final class Addresses extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'URLs';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Be careful. An incorrect value may result in a connection loss.';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'FrontendAddress',
            'BackendAddress',
            'AllowedOrigins'
        ];
    }
}
