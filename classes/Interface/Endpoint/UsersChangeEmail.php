<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\User;
use UOPF\Model\TheCase;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\StringValidator;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Interface\Endpoint;
use UOPF\Exception\ValidationCodeException;

/**
 * Change User Email
 */
final class UsersChangeEmail extends Endpoint {
    public function write(Response $response): User|TheCase {
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
            ),

            'email' => new DictionaryValidatorElement(
                label: 'New Email Address',

                validator: new StringValidator(
                    max: 128,
                    format: 'email'
                )
            )
        ]));

        $caseOrUserOrException = Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedCase = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                throw new ParameterException('Case does not exist.', 'case');

            if ($lockedCase['type'] !== 'auth/email' || $lockedCase['user'] !== $current['id'])
                throw new ParameterException('Invalid case.', 'case');

            try {
                CaseManager::validateLockedEmailValidationCode($lockedCase, $filtered['code']);
            } catch (ValidationCodeException $exception) {
                return new ParameterException($exception->getMessage(), previous: $exception);
            }

            if (!$lockedUser = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            if (!isset($filtered['email']))
                return $lockedUser;

            if ($filtered['email'] === $lockedUser['email'])
                throw new ParameterException('This email address is the same as the current one.', 'email');

            if (UserManager::fetchEntry($filtered['email'], 'email'))
                throw new ParameterException('This email is already used by another user.', 'email');

            try {
                return CaseManager::createEmailValidationCode(
                    type: 'update/email',
                    email: $filtered['email'],
                    user: $lockedUser['id']
                );
            } catch (ValidationCodeException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }
        });

        if ($caseOrUserOrException instanceof User) {
            return $caseOrUserOrException;
        } elseif ($caseOrUserOrException instanceof TheCase) {
            static::sendValidationCode($caseOrUserOrException);
            return $caseOrUserOrException;
        } else {
            throw $caseOrUserOrException;
        }
    }
}
