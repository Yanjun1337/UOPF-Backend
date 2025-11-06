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
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\UserDomainValidator;
use UOPF\Validator\Extension\UserDisplayNameValidator;
use UOPF\Validator\Extension\UserDescriptionValidator;
use UOPF\Exception\ImageUploadException;
use UOPF\Exception\DuplicateUniqueColumnException;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;

/**
 * User Metadata
 */
final class UserMetadata extends Endpoint {
    public function write(Response $response): User {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$user = UserManager::fetchEntry($id))
            $this->throwNotFoundException();

        if (!$this->canEdit($user))
            $this->throwPermissionDeniedException();

        switch ($this->query['name']) {
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

            default:
                $this->throwNotFoundException();
        }
    }

    protected function setDisplayName(User $user): User {
        $filtered = $this->filterBody(new DictionaryValidator([
            'value' => new DictionaryValidatorElement(
                label: 'Display Name',
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
                label: 'Description',
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
}
