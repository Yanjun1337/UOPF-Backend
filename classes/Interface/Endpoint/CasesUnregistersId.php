<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\TheCase;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;

/**
 * Unregistrations ID
 */
final class CasesUnregistersId extends Endpoint {
    public function write(Response $response): TheCase {
        if (!$this->isAdministrative())
            $this->throwPermissionDeniedException();

        $id = $this->filterUserParameterInQuery($this->query['id']);

        $this->filterBody(new DictionaryValidator([
            'status' => new DictionaryValidatorElement(
                label: 'Unregistration Status',
                required: true,

                validator: new EnumerationValidator([
                    'completed'
                ])
            )
        ]));

        Database::transaction(function () use (&$id) {
            if (!$locked = CaseManager::fetchEntryDirectly($id, lock: DatabaseLockType::write))
                $this->throwNotFoundException();

            if ($locked['type'] !== 'unregistration')
                $this->throwNotFoundException();

            if ($locked['status'] === 'completed')
                throw new ParameterException('Unregistration is already completed.');

            CaseManager::updateLockedEntry($locked, [
                'tag' => "completed-{$locked['id']}",
                'modified' => Database::getCurrentTime(),
                'status' => 'completed'
            ]);
        });

        return CaseManager::fetchEntry($id);
    }
}
