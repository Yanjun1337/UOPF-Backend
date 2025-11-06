<?php
declare(strict_types=1);
namespace UOPF\Manager;

use UOPF\Manager;
use UOPF\Request;
use UOPF\Exception;
use UOPF\DatabaseLockType;
use UOPF\ImageUploadParameters;
use UOPF\Model\Image as Model;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Exception\ImageUploadException;
use UOPF\Exception\DuplicateUniqueColumnException;
use Ramsey\Uuid\Uuid;
use Intervention\Image\ImageManager;

/**
 * Image Manager
 */
final class Image extends Manager {
    public function getTableName(): string {
        return 'images';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function uploadFromBody(Request $request, ?ImageUploadParameters $parameters = null): int {
        $temporary = static::createTemporaryFile();

        try {
            if (file_put_contents($temporary, $request->getContent(true)) === false)
                throw new Exception('Failed to write uploaded image into temporary file.');

            return $this->upload($temporary, $parameters);
        } finally {
            unlink($temporary);
        }
    }

    public function upload(string $path, ?ImageUploadParameters $parameters = null): int {
        $metadata = static::parseImageFile($path);

        if (isset($parameters->maximumLengthSize)) {
            $covered = static::createTemporaryFile();

            try {
                try {
                    $editor = ImageManager::imagick()->read($path);
                    $editor->coverDown($parameters->maximumLengthSize, $parameters->maximumLengthSize);
                    $editor->save($covered);
                } catch (\Exception $exception) {
                    throw new Exception('Failed to crop uploaded image.', previous: $exception);
                }

                $originalMetadata = $metadata;
                $metadata = static::parseImageFile($covered);
                $metadata['original'] = $originalMetadata;
            } catch (Exception $exception) {
                unlink($covered);
                throw $exception;
            }

            $sourcePath = $covered;
        } else {
            $sourcePath = $path;
        }

        $image = $this->prepareImageEntry($metadata);

        if (!copy($sourcePath, $image->getPath()))
            throw new Exception('Failed to copy uploaded image to storage directory.');

        if (isset($covered))
            unlink($covered);

        return Database::transaction(function () use (&$image, &$parameters) {
            if (!$locked = $this->fetchEntryDirectly($image['id'], lock: DatabaseLockType::write))
                throw new Exception('Failed to fetch image in database.');

            if ($locked['status'] !== 'uploading')
                throw new Exception('Image in database is invalid.');

            $data = [
                'status' => 'waiting',
                'modified' => Database::getCurrentTime()
            ];

            if (isset($parameters->user)) {
                $lockedUser = UserManager::fetchEntryDirectly(
                    $parameters->user,
                    lock: DatabaseLockType::read
                );

                if ($lockedUser)
                    $data['user'] = $lockedUser['id'];
                else
                    throw new Exception('Unable to fetch image uploader in database.');
            }

            $this->updateLockedEntry($locked, $data);
            return $image['id'];
        });
    }

    public function publishLocked(Model $locked): void {
        $this->updateLockedEntry($locked, [
            'status' => 'publish',
            'modified' => Database::getCurrentTime()
        ]);
    }

    public function trashLocked(Model $locked): void {
        $this->updateLockedEntry($locked, [
            'status' => 'deleting',
            'modified' => Database::getCurrentTime()
        ]);
    }

    protected function prepareImageEntry(array $metadata): Model {
        $time = Database::getCurrentTime();

		$data = [
			'status' => 'uploading',
			'created' => $time,
			'modified' => $time,
			'metadata' => $metadata
        ];

        while (true) {
            try {
                $data['file'] = Uuid::uuid4()->toString();
                return $this->createEntry($data);
            } catch (DuplicateUniqueColumnException $exception) {
                if ($exception->column !== 'file')
                    throw $exception;
            }
        }
    }

    protected static function parseImageFile(string $path): array {
		if (!$information = @getimagesize($path))
            throw new ImageUploadException('Invalid image.');

		$type = $information['mime'];
		$allowed = static::getAllowedImageTypes();

		if (!isset($allowed[$type]))
			throw new ImageUploadException('Unsupported image type.');

		$size = filesize($path);

        if ($size === false || $size <= 0)
            throw new ImageUploadException('Invalid image.');

		$maximumSize = static::getAllowedMaximumImageSize();

		if ($size > $maximumSize) {
			throw new ImageUploadException(sprintf(
				'Image size cannot exceed %s MB.',
				$maximumSize / 1024 / 1024
			));
		}

		return [
			'width' => $information[0],
			'height' => $information[1],
			'size' => $size,
			'type' => $type,
			'extension' => $allowed[$type]
        ];
    }

    protected static function getAllowedImageTypes(): array {
        return [
			'image/jpeg' => 'jpg',
			'image/gif' => 'gif',
			'image/png' => 'png'
        ];
    }

    protected static function getAllowedMaximumImageSize(): int {
        return 20 * 1024 * 1024; // 20 MB.
    }

    protected static function createTemporaryFile(): string {
		if ($path = tempnam(sys_get_temp_dir(), 'image-'))
			return $path;
		else
			throw new Exception( 'Failed to create temporary file.' );
    }
}
