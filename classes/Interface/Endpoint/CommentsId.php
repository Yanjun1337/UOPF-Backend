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
use UOPF\Validator\Extension\RecordContentValidator;
use UOPF\Exception\RecordUpdateException;

/**
 * Comments ID
 */
final class CommentsId extends Endpoint {
    public function read(Response $response): Record {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        if (!$comment = RecordManager::fetchEntry($id))
            $this->throwNotFoundException();

        if ($comment['type'] !== 'comment')
            $this->throwNotFoundException();

        if ($comment['status'] !== 'publish' && !$this->isAdministrative())
            $this->throwPermissionDeniedException();

        return $comment;
    }

    public function write(Response $response): Record {
        $id = $this->filterUserParameterInQuery($this->query['id']);

        $filtered = $this->filterBody(new DictionaryValidator([
            'content' => new DictionaryValidatorElement(
                label: 'Comment Content',
                validator: new RecordContentValidator()
            ),

            'images' => new DictionaryValidatorElement(
                label: 'Comment Images',
                validator: new IdListValidator()
            )
        ]));

        if (empty($filtered))
            throw new ParameterException('No field to edit.');

        Database::transaction(function () use (&$id, &$filtered) {
            if (!$locked = RecordManager::fetchEntryDirectly($id, lock: DatabaseLockType::write))
                $this->throwNotFoundException();

            if ($locked['type'] !== 'comment')
                $this->throwNotFoundException();

            if (!$this->canEdit($locked))
                $this->throwPermissionDeniedException();

            try {
                RecordManager::editLockedEntry(
                    locked: $locked,
                    content: $filtered['content'] ?? null,
                    images: $filtered['images'] ?? null
                );
            } catch (RecordUpdateException $exception) {
                throw new ParameterException($exception->getMessage(), previous: $exception);
            }
        });

        if ($comment = RecordManager::fetchEntry($id))
            return $comment;
        else
            $this->throwInconsistentInternalDataException();
    }
}
