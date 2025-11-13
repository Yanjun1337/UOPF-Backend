<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Services;
use UOPF\DatabaseLockType;
use UOPF\Model\TheCase;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\StringValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\Extension\CaptchaTokenValidator;
use UOPF\Interface\Endpoint;
use UOPF\Exception\ValidationCodeException;

/**
 * Two-factor Authentication
 */
final class UsersAuthentication extends Endpoint {
    public function write(Response $response): TheCase {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'type' => new DictionaryValidatorElement(
                label: 'Authentication Type',
                required: true,

                validator: new EnumerationValidator([
                    'password'
                ])
            )
        ]));

        $case = Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedUser = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            try {
                return CaseManager::createEmailValidationCode(
                    type: "auth/{$filtered['type']}",
                    email: $lockedUser['email'],
                    user: $lockedUser['id']
                );
            } catch (ValidationCodeException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }
        });

        static::sendValidationCode($case);
        return $case;
    }
}
