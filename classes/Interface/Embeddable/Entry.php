<?php
declare(strict_types=1);
namespace UOPF\Interface\Embeddable;

use UOPF\Model;
use UOPF\Exception as SystemException;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Interface\Embeddable;

final class Entry extends Embeddable {
    public function __construct(
        /**
         * The entry ID.
         */
        public readonly int $id,

        /**
         * The entry type.
         */
        public readonly string $type
    ) {}

    public function getEntry(): ?Model {
        switch ($this->type) {
            case 'user':
                return UserManager::fetchEntry($this->id);

            default:
                throw new SystemException('Unsupported embeddable entry type.');
        }
    }
}
