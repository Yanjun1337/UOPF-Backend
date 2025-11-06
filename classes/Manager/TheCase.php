<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Model\TheCase as Model;

/**
 * Case Manager
 */
final class TheCase extends Manager {
    public function getTableName(): string {
        return 'cases';
    }

    public function getModelClass(): string {
        return Model::class;
    }
}
