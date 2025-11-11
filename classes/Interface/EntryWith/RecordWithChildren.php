<?php
declare(strict_types=1);
namespace UOPF\Interface\EntryWith;

use UOPF\RetrievedEntries;
use UOPF\Interface\EntryWith;

final class RecordWithChildren extends EntryWith {
    public function getChildren(int $count): RetrievedEntries {
        return $this->entry->getChildrenRecursively(
            perPage: $count,
            page: 1,
            orderby: 'created',
            order: 'DESC'
        );
    }
}
