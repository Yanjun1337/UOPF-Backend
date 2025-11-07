<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\User;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Exception\ValidationCodeException;
use PragmaRX\Random\Random;

/**
 * Users
 */
final class Users extends Endpoint {
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
            )
        ]));

        $userOrException = Database::transaction(function () use (&$filtered) {
            if (!$locked = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                throw new ParameterException('Case does not exist.', 'case');

            if ($locked['type'] !== 'registration')
                throw new ParameterException('Invalid case.', 'case');

            try {
                CaseManager::validateLockedEmailValidationCode($locked, $filtered['code']);
            } catch (ValidationCodeException $exception) {
                return new ParameterException($exception->getMessage(), previous: $exception);
            }

            CaseManager::closeLockedValidationCode($locked);

            return UserManager::register(
                username: $locked['metadata']['data']['username'],
                displayName: static::generateRandomDisplayName(),
                email: $locked['metadata']['data']['email'],
                passwordHash: $locked['metadata']['data']['passwordHash'],
            );
        });

        if ($userOrException instanceof \Exception)
            throw $userOrException;
        else
            $user = $userOrException;

        if (!isset($this->request->user))
            $this->request->setUser($user);

        static::setTokenOnResponse($response, $user, time() + (60 * 60 * 24));
        return $user;
    }

    protected static function generateRandomDisplayName(): string {
        $random = new Random();
        return $random->get();
    }
}
