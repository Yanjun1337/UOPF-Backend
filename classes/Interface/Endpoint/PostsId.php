<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\Record;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdListValidator;
use UOPF\Validator\Extension\RecordTitleValidator;
use UOPF\Validator\Extension\RecordContentValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Posts ID
 */
final class PostsId extends Endpoint {
    public function read(Response $response): Record {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$post = RecordManager::fetchEntry($id))
            $this->throwNotFoundException();

        if ($post['type'] !== 'post')
            $this->throwNotFoundException();

        if ($post['status'] !== 'publish' && !$this->isAdministrative())
            $this->throwPermissionDeniedException();

        return $post;
    }

    public function write(Response $response): Record {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        if ($current->isBlocked())
            $this->throwBlockedUserException();

        $id = $this->filterUserParameterInQuery($this->query['id']);

        $filtered = $this->filterBody(new DictionaryValidator([
            'title' => new DictionaryValidatorElement(
                label: 'Post Title',
                validator: new RecordTitleValidator()
            ),

            'content' => new DictionaryValidatorElement(
                label: 'Post Content',
                validator: new RecordContentValidator()
            ),

            'images' => new DictionaryValidatorElement(
                label: 'Post Images',
                validator: new IdListValidator()
            )
        ]));

        if (empty($filtered))
            throw new ParameterException('No field to edit.');

        Database::transaction(function () use (&$id, &$filtered) {
            if (!$locked = RecordManager::fetchEntryDirectly($id, lock: DatabaseLockType::write))
                $this->throwNotFoundException();

            if ($locked['type'] !== 'post')
                $this->throwNotFoundException();

            if (!$this->canEdit($locked))
                $this->throwPermissionDeniedException();

            try {
                RecordManager::editLockedEntry(
                    locked: $locked,
                    content: $filtered['content'] ?? null,
                    title: $filtered['title'] ?? null,
                    images: $filtered['images'] ?? null
                );
            } catch (RecordUpdateException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }
        });

        if ($post = RecordManager::fetchEntry($id))
            return $post;
        else
            $this->throwInconsistentInternalDataException();
    }
}
