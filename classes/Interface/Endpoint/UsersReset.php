<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\User;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\UserPasswordValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Interface\Endpoint;
use UOPF\Exception\ValidationCodeException;

/**
 * Reset User Password
 */
final class UsersReset extends Endpoint {
    public function write(Response $response): User {
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
            ),

            'password' => new DictionaryValidatorElement(
                label: 'New Password',
                validator: new UserPasswordValidator()
            )
        ]));

        $userOrException = Database::transaction(function () use (&$filtered) {
            if (!$lockedCase = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                throw new ParameterException('Case does not exist.', 'case');

            if ($lockedCase['type'] !== 'reset')
                throw new ParameterException('Invalid case.', 'case');

            try {
                CaseManager::validateLockedEmailValidationCode($lockedCase, $filtered['code']);
            } catch (ValidationCodeException $exception) {
                return new ParameterException($exception->getMessage(), previous: $exception);
            }

            if (!$lockedUser = UserManager::fetchEntryDirectly($lockedCase['user'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            if (isset($filtered['password']))
                $lockedUser->setMetadata('password', UserManager::createPasswordHash($filtered['password']));
            else
                return $lockedUser;

            CaseManager::closeLockedValidationCode($lockedCase);
            return $lockedUser;
        });

        if ($userOrException instanceof \Exception)
            throw $userOrException;
        else
            return $userOrException;
    }
}
