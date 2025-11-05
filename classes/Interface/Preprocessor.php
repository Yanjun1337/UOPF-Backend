<?php
declare(strict_types=1);
namespace UOPF\Interface;

use UOPF\Model;
use UOPF\Exception as SystemException;
use UOPF\Model\User as UserModel;

/**
 * Return Data Preprocessor
 */
final class Preprocessor {
    public function __construct(
        /**
         * The endpoint context.
         */
        public readonly Endpoint $context
    ) {}

    public function preprocess(mixed $data): mixed {
        if (is_scalar($data) || is_null($data))
            return $data;
        elseif (is_object($data))
            return $this->preprocessInstance($data);
        elseif (is_array($data))
            return $this->preprocessArray($data);
        else
            static::throwUnprocessableException();
    }

    protected function preprocessInstance(object $data): mixed {
        switch (true) {
            case $data instanceof UserModel:
                return $this->preprocessUser($data);

            default:
                static::throwUnprocessableException();
        }
    }

    protected function preprocessArray(array $data): mixed {
        static::throwUnprocessableException();
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

    protected function preprocessEditable(string $field, Model $entry): array {
        $rendered = $entry->renderField($field);
        $preprocessed = compact('rendered');

        if ($this->context->canEdit($entry))
            $preprocessed['raw'] = $entry[$field];

        return $preprocessed;
    }

    protected static function throwUnprocessableException(): never {
        throw new SystemException('Unable to preprocess the data.');
    }
}
