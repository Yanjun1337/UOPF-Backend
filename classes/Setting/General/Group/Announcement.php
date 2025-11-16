<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Group;

use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Setting\Group;

final class Announcement extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'Announcement';

    /**
     * The description of the setting group.
     */
    public string $description {
        get {
            $description = 'If you need to reopen the announcement for all users, check "Push Announcement" and then save.';

            if ($time = SystemMetadataManager::get('announcement/refreshed')) {
                $readableTime = date('Y/m/d H:i:s', $time);
                $description .= " Last push time: <strong>{$readableTime}</strong>.";
            }

            return $description;
        }
    }

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'AnnouncementContent',
            'AnnouncementRefreshed'
        ];
    }
}
