<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Model;
use UOPF\Services;
use UOPF\Exception as SystemException;
use UOPF\Model\User as UserModel;
use UOPF\Model\Image as ImageModel;
use UOPF\Model\Topic as TopicModel;
use UOPF\Model\Record as RecordModel;
use UOPF\Model\TheCase as CaseModel;
use UOPF\Model\Relationship as RelationshipModel;
use UOPF\Facade\Manager\Relationship\User as UserRelationshipManager;
use UOPF\Facade\Manager\Relationship\Like as LikeRelationshipManager;
use UOPF\Facade\Manager\Relationship\Dislike as DislikeRelationshipManager;
use UOPF\Interface\EntryWith\RecordWithChildren;
use UOPF\Interface\Embeddable\FlatList as EmbeddableList;
use UOPF\Interface\Embeddable\Structure as EmbeddableStructure;
use UOPF\Interface\Embeddable\RecursiveStructure as EmbeddableRecursiveStructure;

/**
 * Return Data Preprocessor
 */
final class Preprocessor {
    /**
     * The embedding instructions.
     */
    public readonly array $embedding;

    /**
     * The stack of the current field path.
     */
    protected $stack = [];

    public function __construct(
        /**
         * The endpoint context.
         */
        public readonly Endpoint $context
    ) {
        $this->embedding = $this->parseEmbeddingInstruction();
    }

    public function preprocess(mixed $data): mixed {
        if (is_scalar($data) || is_null($data))
            return $data;
        elseif (is_object($data))
            return $this->preprocess($this->preprocessInstance($data));
        elseif (is_array($data))
            return $this->preprocessArray($data);
        else
            static::throwUnprocessableException();
    }

    protected function preprocessInstance(object $data): mixed {
        switch (true) {
            case $data instanceof Embeddable:
                return $this->preprocessEmbeddable($data);

            case $data instanceof EntryWith:
                return $this->preprocessEntryWith($data);

            case $data instanceof UserModel:
                return $this->preprocessUser($data);

            case $data instanceof ImageModel:
                return $this->preprocessImage($data);

            case $data instanceof CaseModel:
                return $this->preprocessCase($data);

            case $data instanceof RelationshipModel:
                return $this->preprocessRelationship($data);

            case $data instanceof RecordModel:
                return $this->preprocessRecord($data);

            case $data instanceof TopicModel:
                return $this->preprocessTopic($data);

            default:
                static::throwUnprocessableException();
        }
    }

    protected function preprocessArray(array $data): mixed {
        $preprocessed = [];

        foreach ($data as $key => $value) {
            $this->stack[] = $key;
            $preprocessed[$key] = $this->preprocess($value);
            array_pop($this->stack);
        }

        return $preprocessed;
    }

    protected function preprocessEmbeddable(Embeddable $data): mixed {
        $path = str_replace('.[]', '[]', implode('.', $this->stack));
        $embedded = in_array($path, $this->embedding, true);

        switch (true) {
            case $data instanceof EmbeddableStructure:
                if ($embedded)
                    return $data->getStructure();
                else
                    return $data->value;

            case $data instanceof EmbeddableList:
                $preprocessed = [];
                $this->stack[] = '[]';

                foreach ($data->value as $index => $value)
                    $preprocessed[$index] = $this->preprocess($value);

                array_pop($this->stack);
                return $preprocessed;

            case $data instanceof EmbeddableRecursiveStructure:
                if ($embedded) {
                    return $data->getStructure();
                } elseif (in_array($path . '{}', $this->embedding, true)) {
                    $this->embedding[] = "{$path}.{$data->field}{}";
                    return $data->getStructure();
                } else {
                    return $data->value;
                }

            default:
                throw new SystemException('Unsupported type of embeddable value.');
        }
    }

    protected function preprocessEntryWith(EntryWith $data): mixed {
        switch (true) {
            case $data instanceof RecordWithChildren:
                return $this->preprocessRecordWithChildren($data);

            default:
                throw new SystemException('Unsupported type of entry with additional fields.');
        }
    }

    protected function preprocessRecordWithChildren(RecordWithChildren $data): array {
        $preprocessed = $this->preprocessRecord($data->entry);
        $children = $data->getChildren(3);

        $preprocessed['children'] = [
            'list' => new EmbeddableList($children->entries),
            'hasMore' => $children->total > 3
        ];

        return $preprocessed;
    }

    protected function preprocessUser(UserModel $user): array {
        $preprocessed = [
            'id' => $user['id'],
            'username' => $user['username'],
            'displayName' => $this->preprocessEditable('display_name', $user),

            'statistics' => [
                'followings' => $user['_followings'],
                'followers' => $user['_followers'],
                'posts' => $user['_posts'],
                'likes' => $user['_likes'],
                'reposts' => $user['_reposts']
            ]
        ];

        if (isset($user['description']))
            $preprocessed['description'] = $this->preprocessEditable('description', $user);

        if (isset($user['domain']))
            $preprocessed['domain'] = $user['domain'];

        if (($avatar = $user->getImageSourceInMetadata('avatar')) !== null)
            $preprocessed['avatar'] = $avatar;

        if (($background = $user->getImageSourceInMetadata('background')) !== null)
            $preprocessed['background'] = $background;

        if ($this->context->canEdit($user)) {
            $private = [
                'registrationTime' => $user['registered'],
                'email' => $user['email'],
                'canChangeDomain' => !isset($user['domain']),
                'understood' => [], // @TODO
                'unreadNotifications' => intval($user->getMetadata('unreadNotifications'))
            ];

            if ($lastLogin = $user->getMetadata('lastLogin')) {
                $private['lastLogin'] = [
                    'location' => $lastLogin['location'] ?? 'Unknown',
                    'address' => $lastLogin['address'],
                    'time' => $lastLogin['time']
                ];
            }

            $preprocessed['private'] = $private;
        }

        if ($current = $this->context->request->user) {
            if (!$user->is($current)) {
                if ($relationship = UserRelationshipManager::fetch($current['id'], $user['id'])) {
                    $preprocessed['relationship'] = new EmbeddableStructure(
                        $relationship['id'],
                        [Services::getInstance()->userRelationshipManager, 'fetchEntry']
                    );
                }
            }
        }

        return $preprocessed;
    }

    protected function preprocessImage(ImageModel $image): array {
        $preprocessed = [
            'id' => $image['id'],
            'created' => $image['created'],
            'type' => $image['metadata']['type'],
            'source' => $image->getSource(),

            'size' => [
                'width' => $image['metadata']['width'],
                'height' => $image['metadata']['height']
            ]
        ];

        if (isset($image['user'])) {
            $preprocessed['user'] = new EmbeddableStructure(
                $image['user'],
                [Services::getInstance()->userManager, 'fetchEntry']
            );
        }

        if ($this->context->isAdministrative() && isset($image['record']))
            $preprocessed['record'] = $image['record'];

        return $preprocessed;
    }

    protected function preprocessCase(CaseModel $case): array {
        $preprocessed = [
            'id' => $case['id'],
            'type' => $case['type'],
            'status' => $case['status'],
            'modified' => $case['modified']
        ];

        if (isset($case['user'])) {
            $preprocessed['user'] = new EmbeddableStructure(
                $case['user'],
                [Services::getInstance()->userManager, 'fetchEntry']
            );
        }

        if ($case['type'] === 'report' && $this->context->isAdministrative()) {
            $preprocessed['cause'] = $case['metadata']['reason'];

            $preprocessed['record'] = new EmbeddableStructure(
                $case['metadata']['record'],
                [Services::getInstance()->recordManager, 'fetchEntry']
            );
        }

        return $preprocessed;
    }

    protected function preprocessRelationship(RelationshipModel $relationship): array {
        switch ($relationship['type']) {
            case 'u':
                return $this->preprocessUserRelationship($relationship);

            case 'l':
            case 'd':
                return $this->preprocessLikeOrDislikeRelationship($relationship);

            default:
                $this->throwUnprocessableException();
        }
    }

    protected function preprocessUserRelationship(RelationshipModel $relationship): array {
        return [
            'id' => $relationship['id'],
            'created' => $relationship['created'],

            'subject' => new EmbeddableStructure(
                $relationship['subject'],
                [Services::getInstance()->userManager, 'fetchEntry']
            ),

            'object' => new EmbeddableStructure(
                $relationship['object'],
                [Services::getInstance()->userManager, 'fetchEntry']
            )
        ];
    }

    protected function preprocessLikeOrDislikeRelationship(RelationshipModel $relationship): array {
        return [
            'id' => $relationship['id'],
            'created' => $relationship['created'],

            'user' => new EmbeddableStructure(
                $relationship['subject'],
                [Services::getInstance()->userManager, 'fetchEntry']
            ),

            'record' => new EmbeddableStructure(
                $relationship['object'],
                [Services::getInstance()->recordManager, 'fetchEntry']
            )
        ];
    }

    protected function preprocessRecord(RecordModel $record): array {
        if (!$this->context->isAdministrative()) {
            if ($record['status'] === 'trashed') {
                return [
                    'id' => $record['id'],
                    // 'type' => $record['type'],
                    'status' => $record['status']
                ];
            }

            if ($record['status'] == 'blocked') {
                return [
                    'id' => $record['id'],
                    // 'type' => $record['type'],
                    'status' => $record['status'],

                    'user' => new EmbeddableStructure(
                        $record['user'],
                        [Services::getInstance()->userManager, 'fetchEntry']
                    )
                ];
            }
        }

        switch ($record['type']) {
            case 'post':
                return $this->preprocessPost($record);

            case 'comment':
                return $this->preprocessComment($record);

            default:
                $this->throwUnprocessableException();
        }
    }

    protected function preprocessPost(RecordModel $record): array {
        $preprocessed = [
            'id' => $record['id'],
            // 'type' => $record['type'],
            'status' => $record['status'],
            'created' => $record['created'],
            'modified' => $record['modified'],
            'content' => $this->preprocessEditable('content', $record),

            'user' => new EmbeddableStructure(
                $record['user'],
                [Services::getInstance()->userManager, 'fetchEntry']
            ),

            'statistics' => [
                'views' => $record->getViews(),
                'likes' => $record['_likes'],
                'dislikes' => $record['_dislikes'],
                'comments' => $record['_comments'],
                'reposts' => $record['_reposts']
            ]
        ];

        if (isset($record['title']))
            $preprocessed['title'] = $this->preprocessEditable('title', $record);

        if (isset($record['parent'])) {
            $preprocessed['hierarchicalRenderedContent'] = $record->renderHierarchicalContent();

            $preprocessed['parent'] = new EmbeddableRecursiveStructure(
                $record['parent'],
                'parent',
                [Services::getInstance()->recordManager, 'fetchEntry']
            );

            $preprocessed['root'] = new EmbeddableStructure(
                $record->getRoot()['id'],
                [Services::getInstance()->recordManager, 'fetchEntry']
            );
        }

        if (($platform = $record->getPlatform()) !== null)
            $preprocessed['platform'] = $platform;

        if ($current = $this->context->request->user) {
            if ($like = LikeRelationshipManager::fetch($current['id'], $record['id'])) {
                $preprocessed['currentUserLike'] = new EmbeddableStructure(
                    $like['id'],
                    [Services::getInstance()->likeRelationshipManager, 'fetchEntry']
                );
            }

            if ($dislike = DislikeRelationshipManager::fetch($current['id'], $record['id'])) {
                $preprocessed['currentUserDislike'] = new EmbeddableStructure(
                    $dislike['id'],
                    [Services::getInstance()->dislikeRelationshipManager, 'fetchEntry']
                );
            }
        }

        $preprocessed['images'] = $this->preprocessRecordImages($record);
        return $preprocessed;
    }

    protected function preprocessComment(RecordModel $record): array {
        $preprocessed = [
            'id' => $record['id'],
            'type' => $record['type'],
            'status' => $record['status'],
            'created' => $record['created'],
            'modified' => $record['modified'],
            'depth' => $record->getDepth(),
            'content' => $this->preprocessEditable('content', $record),

            'post' => new EmbeddableStructure(
                $record['affiliated_to'],
                [Services::getInstance()->recordManager, 'fetchEntry']
            ),

            'user' => new EmbeddableStructure(
                $record['user'],
                [Services::getInstance()->userManager, 'fetchEntry']
            ),

            'statistics' => [
                'likes' => $record['_likes'],
                'dislikes' => $record['_dislikes'],
                'replies' => $record['_reposts']
            ]
        ];

        if (isset($record['parent'])) {
            $preprocessed['parent'] = new EmbeddableRecursiveStructure(
                $record['parent'],
                'parent',
                [Services::getInstance()->recordManager, 'fetchEntry']
            );
        }

        if (($platform = $record->getPlatform()) !== null)
            $preprocessed['platform'] = $platform;

        if ($current = $this->context->request->user) {
            if ($like = LikeRelationshipManager::fetch($current['id'], $record['id'])) {
                $preprocessed['currentUserLike'] = new EmbeddableStructure(
                    $like['id'],
                    [Services::getInstance()->likeRelationshipManager, 'fetchEntry']
                );
            }

            if ($dislike = DislikeRelationshipManager::fetch($current['id'], $record['id'])) {
                $preprocessed['currentUserDislike'] = new EmbeddableStructure(
                    $dislike['id'],
                    [Services::getInstance()->dislikeRelationshipManager, 'fetchEntry']
                );
            }
        }

        $preprocessed['images'] = $this->preprocessRecordImages($record);
        return $preprocessed;
    }

    protected function preprocessTopic(TopicModel $topic): array {
        return [
            'id' => $topic['id'],
            'label' => $topic['label'],
            'created' => $topic['created'],
            'modified' => $topic['modified'],
            'records' => $topic['_records'],

            'createdFrom' => new EmbeddableStructure(
                $topic['created_from'],
                [Services::getInstance()->recordManager, 'fetchEntry']
            )
        ];
    }

    protected function preprocessEditable(string $field, Model $entry): array {
        $rendered = $entry->renderField($field);
        $preprocessed = compact('rendered');

        if ($this->context->canEdit($entry))
            $preprocessed['raw'] = $entry[$field];

        return $preprocessed;
    }

    protected function preprocessRecordImages(RecordModel $record): EmbeddableList {
        $images = [];

        foreach ($record->fetchImages() as $image) {
            $images[] = new EmbeddableStructure(
                $image['id'],
                [Services::getInstance()->imageManager, 'fetchEntry']
            );
        }

        return new EmbeddableList($images);
    }

    protected function parseEmbeddingInstruction(): array {
        $header = $this->context->request->headers->get('X-API-Embed');

        if (!isset($header))
            return [];

        $instruction = explode(',', $header);
        return array_map('trim', $instruction);
    }

    protected static function throwUnprocessableException(): never {
        throw new SystemException('Unable to preprocess the data.');
    }
}
