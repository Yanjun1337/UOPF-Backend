<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\Relationship;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Facade\Manager\Relationship\Like as LikeRelationshipManager;
use UOPF\Facade\Manager\Relationship\Dislike as DislikeRelationshipManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Exception\DuplicateRelationshipException;
use UOPF\Exception\RelationshipNonexistenceException;

/**
 * Dislike
 */
final class Dislike extends Endpoint {
    public function write(Response $response): Relationship {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'id' => new DictionaryValidatorElement(
                label: 'Post or comment ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        $dislike = Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedSubject = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwNotFoundException();

            if (!$lockedObject = RecordManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('Post or comment to dislike does not exist.', 'id');

            if ($lockedObject['status'] !== 'publish')
                throw new ParameterException('Post or comment to dislike is invalid.', 'id');

            try {
                $dislike = DislikeRelationshipManager::connect(
                    $lockedSubject['id'],
                    $lockedObject['id']
                );
            } catch (DuplicateRelationshipException $exception) {
                throw new ParameterException('User has already disliked the post or comment.', 'id', previous: $exception);
            }

            RecordManager::incrementLockedEntryField($lockedObject, '_dislikes');

            if ($lockedLike = LikeRelationshipManager::fetchDirectly($lockedSubject['id'], $lockedObject['id'], DatabaseLockType::write)) {
                LikeRelationshipManager::deleteLockedEntry($lockedLike);
                RecordManager::incrementLockedEntryField($lockedObject, '_likes', -1);

                if ($lockedRecordUser = UserManager::fetchEntryDirectly($lockedObject['user'], lock: DatabaseLockType::write))
                    UserManager::incrementLockedEntryField($lockedRecordUser, '_likes', -1);
                else
                    $this->throwInconsistentInternalDataException();
            }

            return $dislike;
        });

        $response->setStatusCode(201);
        return $dislike;
    }

    public function delete(Response $response): Relationship {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'id' => new DictionaryValidatorElement(
                label: 'Post or comment ID',
                required: true,
                validator: new IdValidator()
            )
        ]));

        return Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedSubject = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwNotFoundException();

            if (!$lockedObject = RecordManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::write))
                throw new ParameterException('Post or comment from which to withdraw like does not exist.', 'id');

            if ($lockedObject['status'] !== 'publish')
                throw new ParameterException('Post or comment from which to withdraw like is invalid.', 'id');

            try {
                $dislike = DislikeRelationshipManager::disconnect(
                    $lockedSubject['id'],
                    $lockedObject['id']
                );
            } catch (RelationshipNonexistenceException $exception) {
                throw new ParameterException('User has not disliked the post or comment.', 'id', previous: $exception);
            }

            RecordManager::incrementLockedEntryField($lockedObject, '_dislikes', -1);
            return $dislike;
        });
    }
}
