<?php
declare(strict_types=1);
namespace UOPF\Interface\Endpoint;

use UOPF\Response;
use UOPF\DatabaseLockType;
use UOPF\Model\TheCase;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Record as RecordManager;
use UOPF\Facade\Manager\TheCase as CaseManager;
use UOPF\Interface\Endpoint;
use UOPF\Interface\Exception\ParameterException;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\EnumerationValidator;
use UOPF\Validator\DictionaryValidatorElement;
use UOPF\Validator\Extension\IdValidator;
use UOPF\Validator\Extension\ReasonValidator;
use UOPF\Exception\DuplicateUniqueColumnException;

/**
 * Report Record
 */
final class ReportRecord extends Endpoint {
    public function write(Response $response): TheCase {
        if (!$current = $this->request->user)
            $this->throwUnauthorizedException();

        $filtered = $this->filterBody(new DictionaryValidator([
            'type' => new DictionaryValidatorElement(
                label: 'Type',
                required: true,

                validator: new EnumerationValidator([
                    'report'
                ])
            ),

            'id' => new DictionaryValidatorElement(
                label: 'Post ID',
                required: true,
                validator: new IdValidator()
            ),

            'cause' => new DictionaryValidatorElement(
                label: 'Reason',
                required: true,
                validator: new ReasonValidator()
            )
        ]));

        $report = Database::transaction(function () use (&$current, &$filtered) {
            if (!$lockedUser = UserManager::fetchEntryDirectly($current['id'], lock: DatabaseLockType::read))
                $this->throwInconsistentInternalDataException();

            if (!$lockedRecord = RecordManager::fetchEntryDirectly($filtered['id'], lock: DatabaseLockType::read))
                throw new ParameterException('Post or comment to report does not exist.', 'id');

            if ($lockedRecord['status'] !== 'publish')
                throw new ParameterException('Post or comment to report is invalid.', 'id');

            if ($lockedRecord['user'] === $lockedUser['id'])
                throw new ParameterException('Post or comment to report cannot be your own.', 'id');

            $time = Database::getCurrentTime();

            try {
                return CaseManager::createEntry([
                    'type' => 'report',
                    'status' => 'review',
                    'user' => $lockedUser['id'],
                    'tag' => "review-{$lockedRecord['id']}-by-{$lockedUser['id']}",

                    'created' => $time,
                    'modified' => $time,

                    'metadata' => [
                        'record' => $lockedRecord['id'],
                        'reason' => $filtered['cause']
                    ]
                ]);
            } catch (DuplicateUniqueColumnException $exception) {
                throw new ParameterException('You have already reported this post or comment. Please wait for it to be reviewed.', 'id', previous: $exception);
            }
        });

        $response->setStatusCode(201);
        return $report;
    }
}
