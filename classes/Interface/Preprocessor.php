<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Model;
use UOPF\Exception as SystemException;
use UOPF\Model\User as UserModel;
use UOPF\Model\Image as ImageModel;
use UOPF\Interface\Embeddable\Entry as EmbeddableEntry;

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

            case $data instanceof UserModel:
                return $this->preprocessUser($data);

            case $data instanceof ImageModel:
                return $this->preprocessImage($data);

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
            case $data instanceof EmbeddableEntry:
                if ($embedded)
                    return $data->getEntry();
                else
                    return $data->id;

            default:
                throw new SystemException('Unsupported type of embeddable value.');
        }
    }

    protected function preprocessUser(UserModel $user): array {
        $preprocessed = [
            'id' => $user['id'],
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

        if ($this->context->canEdit($user)) {
            $private = [
                'registrationTime' => $user['registered'],
                'username' => $user['username'],
                'email' => $user['email'],
                'canChangeDomain' => !isset($user['domain']),
                'understood' => [], // @TODO
                'unreadNotifications' => intval($user->getMetadata('unreadNotifications'))
            ];

            $preprocessed['private'] = $private;
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

        if (isset($image['user']))
            $preprocessed['user'] = new EmbeddableEntry($image['user'], 'user');

        if ($this->context->isAdministrative() && isset($image['record']))
            $preprocessed['record'] = $image['record'];

        return $preprocessed;
    }

    protected function preprocessEditable(string $field, Model $entry): array {
        $rendered = $entry->renderField($field);
        $preprocessed = compact('rendered');

        if ($this->context->canEdit($entry))
            $preprocessed['raw'] = $entry[$field];

        return $preprocessed;
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
