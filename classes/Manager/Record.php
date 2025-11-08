<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Model\Record as Model;

/**
 * Record Manager
 */
final class Record extends Manager {
    public function getTableName(): string {
        return 'Record';
    }

    public function getModelClass(): string {
        return Model::class;
    }
}
