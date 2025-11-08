<?php
declare(strict_types=1);
namespace UOPF\Manager;

use HTMLPurifier;
use HTMLPurifier_Config as HTMLPurifierConfiguration;
use UOPF\Manager;
use UOPF\Exception;
use UOPF\DatabaseLockType;
use UOPF\Model\Record as Model;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
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
        ?string $userAgent = null
    ): Model {
        return Database::transaction(function () use (
            &$type,
            &$content,
            &$user,
            &$affiliatedTo,
            &$title,
            &$parent,
            &$userAgent
        ) {
            if (!$lockedUser = UserManager::fetchEntryDirectly($user, lock: DatabaseLockType::write))
                throw new RecordUpdateException('Author does not exist.');

            if (isset($title)) {
                if ($type === 'post')
                    $isLong = true;
                else
                    throw new RecordUpdateException('Only post can has title.');
            } else {
                $isLong = false;
            }

            if ($isLong) {
                $sanitized = static::sanitizeLongPostContent($content);
            } else {
                if (mb_strlen($content) > 300)
                    throw new RecordUpdateException('Content cannot exceed 300 characters.');

                $sanitized = $content;
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
                if ($isLong)
                    throw new RecordUpdateException('Long post must be top-level.');

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
                'content' => $sanitized,
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

            // if (isset($lockedAffiliatedTo)) {
            //     $this->incrementLockedEntryField($lockedAffiliatedTo, '_comments');

            //     if (!$lockedAffiliatedToUser = UserManager::fetchEntryDirectly($lockedAffiliatedTo['user'], lock: DatabaseLockType::write))
            //         throw new Exception('Author of record that this record is affiliated to does not exist.');
            // }

            return $record;
        });
    }

    protected static function sanitizeLongPostContent(string $value): string {
        return static::purifyLongPostContent($value);
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
