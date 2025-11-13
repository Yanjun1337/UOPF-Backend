<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Group;

use UOPF\Setting\Group;

final class Title extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Title';

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'Title'
        ];
    }
}
