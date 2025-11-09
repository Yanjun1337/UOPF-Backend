<?php
declare(strict_types=1);
namespace UOPF\Manager;

use DOMDocument;
use HTMLPurifier;
use HTMLPurifier_Config as HTMLPurifierConfiguration;
use UOPF\Manager;
use UOPF\Exception;
use UOPF\DatabaseLockType;
use UOPF\Model\Record as Model;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Facade\Manager\Topic as TopicManager;
use UOPF\Exception\RecordUpdateException;

/**
 * Record Manager
 */
final class Record extends Manager {
    public function getTableName(): string {
        return 'records';
    }

    public function getModelClass(): string {
        return Model::class;
    }

    public function publish(
        string $type,
        string $content,
        int $user,
        ?int $affiliatedTo = null,
        ?string $title = null,
        ?int $parent = null,
        ?string $userAgent = null,
        array $images = [],
        ?int $contextUser = null
    ): Model {
        return Database::transaction(function () use (
            &$type,
            &$content,
            &$user,
            &$affiliatedTo,
            &$title,
            &$parent,
            &$userAgent,
            &$images
        ) {
            if (!$lockedUser = UserManager::fetchEntryDirectly($user, lock: DatabaseLockType::write))
                throw new RecordUpdateException('Author does not exist.');

            if (isset($title)) {
                if ($type !== 'post')
                    throw new RecordUpdateException('Only post can has title.');

                if (isset($parent))
                    throw new RecordUpdateException('Long post must be top-level.');

                $isLong = true;
            } else {
                $isLong = false;
            }

            if ($isLong)
                $maximumImages = 100;
            elseif ($type === 'comment')
                $maximumImages = 1;
            elseif (isset($parent))
                $maximumImages = 1;
            else
                $maximumImages = 9;

            if (count($images) > $maximumImages)
                throw new RecordUpdateException("Number of images cannot exceed {$maximumImages}.");

            $lockedImages = [];

            foreach ($images as $image) {
                if (!$lockedImage = ImageManager::fetchEntryDirectly($image, lock: DatabaseLockType::write))
                    throw new RecordUpdateException("Image ({$image}) does not exist.");

                if ($lockedImage['status'] !== 'waiting')
                    throw new RecordUpdateException("Image ({$image}) is invalid.");

                if (!$lockedImage->canBeEditedBy($lockedUser))
                    throw new RecordUpdateException("Permission denied to use image ({$image}).");

                $lockedImages[] = $lockedImage;
            }

            if ($isLong) {
                $sanitizedContent = static::sanitizeLongPostContent($content, $lockedImages);
                $topics = TopicManager::extractFromHTML($sanitizedContent);
            } else {
                if (mb_strlen($content) > 300)
                    throw new RecordUpdateException('Content cannot exceed 300 characters.');

                $sanitizedContent = $content;
                $topics = TopicManager::extractFromText($sanitizedContent);
            }

            if ($type === 'comment') {
                if (!isset($affiliatedTo))
                    throw new RecordUpdateException('Comment must be affiliated to a post.');

                if (!$lockedAffiliatedTo = $this->fetchEntryDirectly($affiliatedTo, lock: DatabaseLockType::write))
                    throw new RecordUpdateException('Post the comment is affiliated to does not exist.');

                if ($lockedAffiliatedTo['type'] !== 'post' || $lockedAffiliatedTo['status'] !== 'publish')
                    throw new RecordUpdateException('Post the comment is affiliated to is invalid.');
            } elseif (isset($affiliatedTo)) {
                throw new RecordUpdateException('Only comment can be affiliated to a post.');
            }

            if (isset($parent)) {
                if (!$lockedParent = $this->fetchEntryDirectly($parent, lock: DatabaseLockType::write))
                    throw new RecordUpdateException('Parent does not exist.');

                if ($lockedParent['type'] !== $type || $lockedParent['status'] !== 'publish')
                    throw new RecordUpdateException('Parent is invalid.');

                if (isset($lockedAffiliatedTo) && $lockedAffiliatedTo['id'] !== $lockedParent['id'])
                    throw new RecordUpdateException('Parent is affiliated to a different post.');
            }

            $time = Database::getCurrentTime();

            $data = [
                'user' => $lockedUser['id'],
                'content' => $sanitizedContent,
                'type' => $type,
                'status' => 'publish',
                'created' => $time,
                'modified' => $time
            ];

            if (isset($title))
                $data['title'] = $title;

            if (isset($lockedAffiliatedTo))
                $data['affiliated_to'] = $lockedAffiliatedTo['id'];

            if (isset($lockedParent))
                $data['parent'] = $lockedParent['id'];

            // Excessive content will be truncated by the database.
            if (isset($userAgent))
                $data['user_agent'] = $userAgent;

            $record = $this->createEntry($data);

            if (isset($lockedParent)) {
                $this->incrementLockedEntryField($lockedParent, '_reposts');

                if ($type === 'post') {
                    if (!$lockedParentUser = UserManager::fetchEntryDirectly($lockedParent['user'], lock: DatabaseLockType::write))
                        throw new Exception('Author of parent record does not exist.');

                    UserManager::incrementLockedEntryField($lockedParentUser, '_reposts');
                }
            }

            if ($type === 'post')
                UserManager::incrementLockedEntryField($lockedUser, '_posts');

            if (isset($lockedAffiliatedTo)) {
                $this->incrementLockedEntryField($lockedAffiliatedTo, '_comments');

                // if (!$lockedAffiliatedToUser = UserManager::fetchEntryDirectly($lockedAffiliatedTo['user'], lock: DatabaseLockType::write))
                    // throw new Exception('Author of record that this record is affiliated to does not exist.');
            }

            foreach ($lockedImages as $index => $lockedImage) {
                ImageManager::updateLockedEntry($lockedImage, [
                    'status' => 'publish',
                    'record' => $record['id'],
                    'position' => $index + 1,
                    'modified' => $time
                ]);
            }

            TopicManager::engageRecordIn($topics, $record['id']);
            return $record;
        });
    }

    public function trashLocked(Model $locked): void {
        $allowedStatus = [
            'publish',
            'blocked'
        ];

        if (!in_array($locked['status'], $allowedStatus, true))
            throw new RecordUpdateException('Invalid status.');

        if ($locked['type'] === 'comment') {
            $children = $this->queryEntries([
                'type' => $locked['type'],
                'status' => $allowedStatus,
                'parent' => $locked['id']
            ]);

            foreach ($children->entries as $entry) {
                if ($lockedChild = $this->fetchEntryDirectly($entry['id'], lock: DatabaseLockType::write))
                    $this->trashLocked($lockedChild);
                else
                    throw new Exception('Failed to fetch child comment.');
            }
        }

        $topics = $locked->extractTopics();
        TopicManager::withdrawRecordFrom($topics);

        if (isset($locked['parent'])) {
            if (!$lockedParent = $this->fetchEntryDirectly($locked['parent'], lock: DatabaseLockType::write))
                throw new Exception('Parent does not exist.');

            $this->incrementLockedEntryField($lockedParent, '_reposts', -1);

            if ($lockedParent['type'] === 'post') {
                if ($lockedParentUser = UserManager::fetchEntryDirectly($lockedParent['user'], lock: DatabaseLockType::write))
                    UserManager::incrementLockedEntryField($lockedParentUser, '_reposts', -1);
                else
                    throw new Exception('Author of parent record does not exist.');
            }
        }

        if ($locked['type'] === 'post') {
            if ($lockedUser = UserManager::fetchEntryDirectly($locked['user'], lock: DatabaseLockType::write))
                UserManager::incrementLockedEntryField($lockedUser, '_posts', -1);
            else
                throw new Exception('Author does not exist.');
        } elseif ($locked['type'] === 'comment') {
            if ($lockedAffiliatedTo = $this->fetchEntryDirectly($locked['affiliated_to'], lock: DatabaseLockType::write))
                $this->incrementLockedEntryField($lockedAffiliatedTo, '_comments', -1);
            else
                throw new Exception('Post the comment is affiliated to does not exist.');
        }

        $this->updateLockedEntry($locked, [
            'status' => 'trashed',
            'modified' => Database::getCurrentTime()
        ]);
    }

    protected static function sanitizeLongPostContent(string $value, array $images = []): string {
        $purified = static::purifyLongPostContent($value);

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="utf-8"?>' . $purified);

        $sourceIndexedImages = [];

        foreach ($images as $image) {
            $source = $image->getSource();
            $sourceIndexedImages[$source] = $image;
        }

        foreach ($document->getElementsByTagName('img') as $tag) {
            $source = $tag->attributes['src']->value;

            if (!isset($sourceIndexedImages[$source]))
                $tag->parentNode->removeChild($tag);
        }

        $node = $document->getElementsByTagName('body')->item(0);
        return substr($document->saveHTML($node), strlen('<body>'), (strlen('</body>') * -1));
    }

    protected static function purifyLongPostContent(string $value): string {
        $allowedTags = [
            'img' => [
                'src',
                'alt'
            ],

            'em' => [],
            'b' => [],
            'strong' => [],
            'cite' => [],
            'blockquote' => [],
            'code' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'dl' => [],
            'dt' => [],
            'dd' => [],
            'p'  => [],
            'br' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'h5' => [],
            'h6' => [],
            'span' => [],

            // Public attributes.
            '*' => ['style']
        ];

        $configuration = HTMLPurifierConfiguration::createDefault();
        $configuration->set('Core.EnableIDNA', true);
        $configuration->set('AutoFormat.AutoParagraph', true);
        $configuration->set('HTML.Doctype', 'XHTML 1.0 Strict');

        $allowed = implode(',', static::flattenPurifierAllowedConfiguration($allowedTags));
        $configuration->set('HTML.Allowed', $allowed);

        $purifier = new HTMLPurifier($configuration);
        return $purifier->purify($value);
    }

    protected static function flattenPurifierAllowedConfiguration(array $allowedTags): array {
        $allowed = [];

        foreach ($allowedTags as $tag => $attributes) {
            if ($attributes) {
                $extra = implode('|', $attributes);
                $allowed[] = "{$tag}[{$extra}]";
            } else {
                $allowed[] = $tag;
            }
        }

        return $allowed;
    }
}
