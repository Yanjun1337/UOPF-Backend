<?php
declare(strict_types=1);
namespace UOPF\Interface\EntryWith;

use UOPF\Model\User;
use UOPF\Interface\EntryWith;

final class UserWithRelationship extends EntryWith {
    public function __construct(
        User $user,

        /**
         * The user who has relationships with the retrieved user.
         */
        public readonly int $with
    ) {
        parent::__construct($user);
    }
}
