<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\Relationship;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Relationship\User as UserRelationshipManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Exception\DuplicateRelationshipException;
use UOPF\Exception\RelationshipNonexistenceException;

/**
 * Users Relationship
 */
final class UsersRelationship extends Endpoint {
    public function write(Response $response): Relationship {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        $filtered = $this->filterBody(new DictionaryValidator([
            'id' => new DictionaryValidatorElement(
                label: 'ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        $relationship = Database::transaction(function () use (&$id, &$filtered) {
            if (!$lockedSubject = UserManager::fetchEntryDirectly($id, lock: DatabaseLockType::write))
                $this->throwNotFoundException();

            if (!$this->canEdit($lockedSubject))
                $this->throwPermissionDeniedException();

            if (!$lockedObject = UserManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('User to follow does not exist.', 'id');

            if ($lockedObject->is($lockedSubject))
                throw new ParameterException('User cannot follow themselves.', 'id');

            try {
                $relationship = UserRelationshipManager::connect(
                    $lockedSubject['id'],
                    $lockedObject['id']
                );
            } catch (DuplicateRelationshipException $exception) {
                throw new ParameterException('User has already followed the target.', 'id', previous: $exception);
            }

            UserManager::incrementLockedEntryField($lockedSubject, '_followings');
            UserManager::incrementLockedEntryField($lockedObject, '_followers');

            return $relationship;
        });

        $response->setStatusCode(201);
        return $relationship;
    }

    public function delete(Response $response): Relationship {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        $filtered = $this->filterBody(new DictionaryValidator([
            'id' => new DictionaryValidatorElement(
                label: 'ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        return Database::transaction(function () use (&$id, &$filtered) {
            if (!$lockedSubject = UserManager::fetchEntryDirectly($id, lock: DatabaseLockType::write))
                $this->throwNotFoundException();

            if (!$this->canEdit($lockedSubject))
                $this->throwPermissionDeniedException();

            if (!$lockedObject = UserManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('User to unfollow does not exist.', 'id');

            try {
                $relationship = UserRelationshipManager::disconnect(
                    $lockedSubject['id'],
                    $lockedObject['id']
                );
            } catch (RelationshipNonexistenceException $exception) {
                throw new ParameterException('User has not followed the target.', 'id', previous: $exception);
            }

            UserManager::incrementLockedEntryField($lockedSubject, '_followings', -1);
            UserManager::incrementLockedEntryField($lockedObject, '_followers', -1);

            return $relationship;
        });
    }
}
