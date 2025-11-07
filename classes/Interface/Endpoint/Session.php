<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\Services;
use UOPF\Model\User;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\StringValidator;
use UOPF\Validator\BooleanValidator;
use UOPF\Validator\Extension\UsernameValidator;
use UOPF\Validator\Extension\UserPasswordValidator;
use UOPF\Validator\Extension\CaptchaTokenValidator;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;

/**
 * Session
 */
final class Session extends Endpoint {
    public function read(Response $response): array {
        $payload = [
            'frontend' => SystemMetadataManager::get('frontendAddress'),
            'backend' => SystemMetadataManager::get('backendAddress')
        ];

        if (isset($this->request->user))
            $payload['user'] = $this->request->user['id'];

        return $payload;
    }

    public function write(Response $response): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'account' => new DictionaryValidatorElement(
                label: 'Account',

                validator: new StringValidator(
                    allowEmpty: false,
                    max: 128
                )
            ),

            'email' => new DictionaryValidatorElement(
                label: 'Email',

                validator: new StringValidator(
                    max: 128,
                    format: 'email'
                )
            ),

            'username' => new DictionaryValidatorElement(
                label: 'Username',
                validator: new UsernameValidator()
            ),

            'password' => new DictionaryValidatorElement(
                label: 'Password',
                required: true,
                validator: new UserPasswordValidator()
            ),

            'remember' => new DictionaryValidatorElement(
                label: 'Remember',
                default: false,
                validator: new BooleanValidator()
            ),

            'captcha' => new DictionaryValidatorElement(
                label: 'Captcha Token',
                required: !Services::isDevelopment(),
                validator: new CaptchaTokenValidator()
            )
        ]));

        $identifierKeys = [
            'account',
            'email',
            'username'
        ];

        foreach ($identifierKeys as $key) {
            if (isset($filtered[$key])) {
                $identifierKey = $key;
                break;
            }
        }

        if (!isset($identifierKey))
            throw new ParameterException('No available account identifier.');

        if (isset($filtered['captcha']))
            $this->validateCaptcha($filtered['captcha']);

        if ($identifierKey === 'account') {
            if (strpos($filtered[$identifierKey], '@') === false)
                $identifierField = 'username';
            else
                $identifierField = 'email';
        } else {
            $identifierField = $identifierKey;
        }

        if (!$user = UserManager::fetchEntry($filtered[$identifierKey], $identifierField))
            throw new ParameterException('User does not exist.', $identifierKey);

        if (!password_verify($filtered['password'], $user->getMetadata('password')))
            throw new ParameterException('Incorrect password.', $identifierKey);

        $days = $filtered['remember'] ? 365 : 1;
        static::setTokenOnResponse($response, $user, time() + (60 * 60 * 24 * $days));

        $this->request->setUser($user);
        return $user;
    }
}
