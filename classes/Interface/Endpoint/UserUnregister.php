<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\TheCase;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Interface\Endpoint;
use UOPF\Exception\ValidationCodeException;
use UOPF\Exception\DuplicateUniqueColumnException;

/**
 * User Unregistration
 */
final class UserUnregister extends Endpoint {
    public function read(Response $response): TheCase {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $case = CaseManager::findEntry([
            'type' => 'unregistration',
            'tag' => "unregistration-{$current['id']}"
        ]);

        if ($case)
            return $case;
        else
            $this->throwNotFoundException();
    }

    public function write(Response $response): TheCase {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'case' => new DictionaryValidatorElement(
                label: 'Case',
                required: true,
                validator: new IdValidator()
            ),

            'code' => new DictionaryValidatorElement(
                label: 'Validation Code',
                required: true,
                validator: new ValidationCodeValidator()
            )
        ]));

        $caseOrException = Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedCase = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                throw new ParameterException('Case does not exist.', 'case');

            if ($lockedCase['type'] !== 'auth/unregistration' || $lockedCase['user'] !== $current['id'])
                throw new ParameterException('Invalid case.', 'case');

            try {
                CaseManager::validateLockedEmailValidationCode($lockedCase, $filtered['code']);
            } catch (ValidationCodeException $exception) {
                return new ParameterException($exception->getMessage(), previous: $exception);
            }

            if (!$lockedUser = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            $time = Database::getCurrentTime();

            try {
                return CaseManager::createEntry([
                    'type' => 'unregistration',
                    'status' => 'review',
                    'user' => $lockedUser['id'],
                    'tag' => "unregistration-{$lockedUser['id']}",

                    'created' => $time,
                    'modified' => $time
                ]);
            } catch (DuplicateUniqueColumnException $exception) {
                throw new ParameterException('You have already requested to unregister your account. Please wait for it to be reviewed.', previous: $exception);
            }
        });

        if ($caseOrException instanceof \Exception)
            throw $caseOrException;
        else
            $case = $caseOrException;

        $response->setStatusCode(201);
        return $case;
    }
}
