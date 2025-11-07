<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Services;
use UOPF\Response;
use UOPF\Model\TheCase as TheCase;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Exception\EmailSendingException;
use UOPF\Exception\ValidationCodeException;
use UOPF\Validator\StringValidator;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\UsernameValidator;
use UOPF\Validator\Extension\UserPasswordValidator;
use UOPF\Validator\Extension\CaptchaTokenValidator;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception as InterfaceException;
use UOPF\Interface\Exception\ParameterException;

/**
 * User Registration
 */
final class UsersRegister extends Endpoint {
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

            'username' => new DictionaryValidatorElement(
                label: 'Username',
                required: true,
                validator: new UsernameValidator()
            ),

            'password' => new DictionaryValidatorElement(
                label: 'Password',
                required: true,
                validator: new UserPasswordValidator()
            ),

            'captcha' => new DictionaryValidatorElement(
                label: 'Captcha Token',
                required: !Services::isDevelopment(),
                validator: new CaptchaTokenValidator()
            )
        ]));

        if (isset($filtered['captcha']))
            $this->validateCaptcha($filtered['captcha']);

        if (UserManager::fetchEntry($filtered['email'], 'email'))
            throw new ParameterException('This email is already used by another user.', 'email');

        if (UserManager::fetchEntry($filtered['username'], 'username'))
            throw new ParameterException('This username is already used by another user.', 'username');

        $data = [
            'email' => $filtered['email'],
            'username' => $filtered['username'],
            'passwordHash' => UserManager::createPasswordHash($filtered['password'])
        ];

        try {
            $case = CaseManager::createEmailValidationCode(
                type: 'registration',
                email: $filtered['email'],
                data: $data
            );
        } catch (ValidationCodeException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        try {
            $case->sendValidationCode();
        } catch (EmailSendingException $exception) {
            throw new InterfaceException('Failed to send email.', 500, previous: $exception);
        }

        return $case;
    }
}
