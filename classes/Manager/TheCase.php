<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Exception;
use UOPF\DatabaseLockType;
use UOPF\Model\TheCase as Model;
use UOPF\Facade\Database;
use UOPF\Exception\ValidationCodeException;

/**
 * Case Manager
 */
final class TheCase extends Manager {
    public function getTableName(): string {
        return 'cases';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function createEmailValidationCode(
        string $type,
        string $email,
        ?int $user = null,
        ?array $data = null
    ): Model {
        return Database::transaction(function () use (&$type, &$email, &$user, &$data) {
            $conditions = [
                'type' => $type,
                'tag' => $email,
                'user' => $user
            ];

            $metadata = [
                'attempts' => 5,
                'email' => $email,
                'code' => static::generateValidationCode(6)
            ];

            if (isset($data))
                $metadata['data'] = $data;

            if ($locked = $this->findEntryDirectly($conditions, DatabaseLockType::write)) {
                if (!$locked->isExpired(60))
                    throw new ValidationCodeException('Validation code cannot be resent within 1 minute.');

                $this->updateLockedEntry($locked, [
                    'modified' => Database::getCurrentTime(),
                    'status' => 'waiting',
                    'metadata' => $metadata
                ]);

                if ($entry = $this->fetchEntryDirectly($locked['id']))
                    return $entry;
                else
                    throw new Exception('Failed to fetch updated case.');
            } else {
                $time = Database::getCurrentTime();

                return $this->createEntry([
                    'type' => $type,
                    'status' => 'waiting',
                    'created' => $time,
                    'modified' => $time,
                    'tag' => $email,
                    'user' => $user,
                    'metadata' => $metadata
                ]);
            }
        });
    }

    public function validateLockedEmailValidationCode(Model $locked, string $value): void {
        if ($locked['status'] !== 'waiting')
            throw new ValidationCodeException('Invalid case.');

        if ($locked->isExpired(60 * 30))
            throw new ValidationCodeException('Validation code has expired.');

        $metadata = $locked['metadata'];
        $limited = isset($metadata['attempts']);

        if (!isset($metadata['code']) || !is_string($metadata['code']) || strlen($metadata['code']) <= 0)
            throw new ValidationCodeException('No validation code.');

        if ($limited && $metadata['attempts'] <= 0)
            throw new ValidationCodeException('Validation code has become invalid due to too many attempts.');

        if (!hash_equals($metadata['code'], $value)) {
            if ($limited) {
                --$metadata['attempts'];
                $this->updateLockedEntry($locked, compact('metadata'));
            }

            throw new ValidationCodeException('Incorrect validation code.');
        }
    }

    public function closeLockedValidationCode(Model $locked): void {
        $this->updateLockedEntry($locked, ['status' => 'closed']);
    }

    protected static function generateValidationCode(int $length): string {
        $code = '';

        for ($index = 0; $index < $length; ++$index)
            $code .= strval(random_int(0, 9));

        return $code;
    }
}
