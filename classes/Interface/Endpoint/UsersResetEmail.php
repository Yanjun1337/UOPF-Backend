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
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\Extension\CaptchaTokenValidator;
use UOPF\Interface\Endpoint;
use UOPF\Exception\ValidationCodeException;

/**
 * Reset User Password by Email
 */
final class UsersResetEmail extends Endpoint {
    public function write(Response $response): TheCase {
        $filtered = $this->filterBody(new DictionaryValidator([
            'email' => new DictionaryValidatorElement(
                label: 'Email',
                required: true,

                validator: new StringValidator(
                    max: 128,
                    format: 'email'
                )
            ),

            'captcha' => new DictionaryValidatorElement(
                label: 'Captcha Token',
                required: !Services::isDevelopment(),
                validator: new CaptchaTokenValidator()
            )
        ]));

        if (isset($filtered['captcha']))
            $this->validateCaptcha($filtered['captcha']);

        $case = Database::transaction(function () use (&$filtered) {
            if (!$lockedUser = UserManager::fetchEntryDirectly($filtered['email'], 'email', DatabaseLockType::read))
                throw new ParameterException('No user is associated with this email address.');

            try {
                return CaseManager::createEmailValidationCode(
                    type: 'reset',
                    email: $filtered['email'],
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
