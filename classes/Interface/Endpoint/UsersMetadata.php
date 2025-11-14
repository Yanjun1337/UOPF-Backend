<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\ImageUploadParameters;
use UOPF\Model\User;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\UsernameValidator;
use UOPF\Validator\Extension\UserDomainValidator;
use UOPF\Validator\Extension\UserPasswordValidator;
use UOPF\Validator\Extension\ValidationCodeValidator;
use UOPF\Validator\Extension\UserDisplayNameValidator;
use UOPF\Validator\Extension\UserDescriptionValidator;
use UOPF\Exception\ImageUploadException;
use UOPF\Exception\ValidationCodeException;
use UOPF\Exception\DuplicateUniqueColumnException;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;

/**
 * User Metadata
 */
final class UsersMetadata extends Endpoint {
    public function write(Response $response): User {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$user = UserManager::fetchEntry($id))
            $this->throwNotFoundException();

        if (!$this->canEdit($user))
            $this->throwPermissionDeniedException();

        switch ($this->query['name']) {
            case 'username':
                return $this->setUsername($user);

            case 'displayName':
                return $this->setDisplayName($user);

            case 'description':
                return $this->setDescription($user);

            case 'domain':
                return $this->setDomain($user);

            case 'avatar':
                return $this->setAvatar($user);

            case 'background':
                return $this->setBackground($user);

            case 'password':
                return $this->setPassword($user);

            default:
                $this->throwNotFoundException();
        }
    }

    protected function setUsername(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'value' => new DictionaryValidatorElement(
                label: 'New Username',
                required: true,
                validator: new UsernameValidator()
            )
        ]));

        try {
            $data = ['username' => $filtered['value']];
            return UserManager::updateEntry($user['id'], $data);
        } catch (DuplicateUniqueColumnException) {
            throw new ParameterException('This username is already used by another user.');
        }
    }

    protected function setDisplayName(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'value' => new DictionaryValidatorElement(
                label: 'New Display Name',
                required: true,
                validator: new UserDisplayNameValidator()
            )
        ]));

        try {
            $data = ['display_name' => $filtered['value']];
            return UserManager::updateEntry($user['id'], $data);
        } catch (DuplicateUniqueColumnException) {
            throw new ParameterException('This display name is already used by another user.');
        }
    }

    protected function setDescription(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'value' => new DictionaryValidatorElement(
                label: 'New Description',
                required: true,
                validator: new UserDescriptionValidator()
            )
        ]));

        if (strlen($filtered['value']) > 0)
            $description = $filtered['value'];
        else
            $description = null;

        $data = compact('description');
        return UserManager::updateEntry($user['id'], $data);
    }

    protected function setDomain(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'value' => new DictionaryValidatorElement(
                label: 'Personal Domain',
                required: true,
                validator: new UserDomainValidator()
            )
        ]));

        return Database::transaction(function () use (&$user, &$filtered) {
            if (!$locked = UserManager::fetchEntryDirectly($user['id'], lock: DatabaseLockType::write))
                $this->throwInconsistentInternalDataException();

            if (isset($locked['domain']))
                throw new ParameterException('Personal domain can be set only once.');

            try {
                $data = ['domain' => $filtered['value']];
                return UserManager::updateEntry($user['id'], $data);
            } catch (DuplicateUniqueColumnException) {
                throw new ParameterException('This personal domain is already used by another user.');
            }
        });
    }

    protected function setAvatar(User $user): User {
        $parameters = new ImageUploadParameters(
            user: $user['id'],
            maximumLengthSize: 500
        );

        try {
            $uploaded = ImageManager::uploadFromBody($this->request, $parameters);
        } catch (ImageUploadException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        return Database::transaction(function () use (&$user, &$uploaded) {
            if (!$lockedImage = ImageManager::fetchEntryDirectly($uploaded, lock: DatabaseLockType::write))
                $this->throwInconsistentInternalDataException();

            if ($lockedImage['status'] !== 'waiting')
                $this->throwInconsistentInternalDataException();

            if (!$lockedUser = UserManager::fetchEntryDirectly($user['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            if ($lockedMetadata = UserMetadataManager::fetchDirectly('avatar', $user['id'], DatabaseLockType::write)) {
                $value = $lockedMetadata->getDecodedValue();

                if (!$lockedCurrentImage = ImageManager::fetchEntryDirectly($value, lock: DatabaseLockType::write))
                    $this->throwInconsistentInternalDataException();

                UserMetadataManager::setLocked($lockedMetadata, $lockedImage['id']);
                ImageManager::trashLocked($lockedCurrentImage);
            } else {
                UserMetadataManager::add('avatar', $lockedImage['id'], $user['id']);
            }

            ImageManager::publishLocked($lockedImage);
            return $lockedUser;
        });
    }

    protected function setBackground(User $user): User {
        $parameters = new ImageUploadParameters(
            user: $user['id']
        );

        try {
            $uploaded = ImageManager::uploadFromBody($this->request, $parameters);
        } catch (ImageUploadException $exception) {
            throw new ParameterException($exception->getMessage(), previous: $exception);
        }

        return Database::transaction(function () use (&$user, &$uploaded) {
            if (!$lockedImage = ImageManager::fetchEntryDirectly($uploaded, lock: DatabaseLockType::write))
                $this->throwInconsistentInternalDataException();

            if ($lockedImage['status'] !== 'waiting')
                $this->throwInconsistentInternalDataException();

            if (!$lockedUser = UserManager::fetchEntryDirectly($user['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            if ($lockedMetadata = UserMetadataManager::fetchDirectly('background', $user['id'], DatabaseLockType::write)) {
                $value = $lockedMetadata->getDecodedValue();

                if (!$lockedCurrentImage = ImageManager::fetchEntryDirectly($value, lock: DatabaseLockType::write))
                    $this->throwInconsistentInternalDataException();

                UserMetadataManager::setLocked($lockedMetadata, $lockedImage['id']);
                ImageManager::trashLocked($lockedCurrentImage);
            } else {
                UserMetadataManager::add('background', $lockedImage['id'], $user['id']);
            }

            ImageManager::publishLocked($lockedImage);
            return $lockedUser;
        });
    }

    protected function setPassword(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'case' => new DictionaryValidatorElement(
                label: 'Case',
                validator: new IdValidator()
            ),

            'code' => new DictionaryValidatorElement(
                label: 'Validation Code',
                validator: new ValidationCodeValidator()
            ),

            'value' => new DictionaryValidatorElement(
                label: 'New Password',
                validator: new UserPasswordValidator()
            )
        ]));

        $userOrException = Database::transaction(function () use (&$user, &$filtered) {
            if (isset($filtered['case'])) {
                if (!isset($filtered['code']))
                    throw new ParameterException('Validation code is required.', 'code');

                if (!$lockedCase = CaseManager::fetchEntryDirectly($filtered['case'], lock: DatabaseLockType::write))
                    throw new ParameterException('Case does not exist.', 'case');

                if ($lockedCase['type'] !== 'auth/password' || $lockedCase['user'] !== $user['id'])
                    throw new ParameterException('Invalid case.', 'case');

                try {
                    CaseManager::validateLockedEmailValidationCode($lockedCase, $filtered['code']);
                } catch (ValidationCodeException $exception) {
                    return new ParameterException($exception->getMessage(), previous: $exception);
                }

                if (!$lockedUser = UserManager::fetchEntryDirectly($lockedCase['user'], lock: DatabaseLockType::read))
                    $this->throwInconsistentInternalDataException();

                if (isset($filtered['value']))
                    $lockedUser->setMetadata('password', UserManager::createPasswordHash($filtered['value']));
                else
                    return $lockedUser;

                CaseManager::closeLockedValidationCode($lockedCase);
                return $lockedUser;
            } else {
                if (!$this->isAdministrative())
                    $this->throwPermissionDeniedException();

                if (!isset($filtered['value']))
                    throw new ParameterException('New password is required.', 'value');

                if (!$lockedUser = UserManager::fetchEntryDirectly($user['id'], lock: DatabaseLockType::read))
                    $this->throwInconsistentInternalDataException();

                $lockedUser->setMetadata('password', UserManager::createPasswordHash($filtered['value']));
                return $lockedUser;
            }
        });

        if ($userOrException instanceof \Exception)
            throw $userOrException;
        else
            return $userOrException;
    }
}
