<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\Utilities;
use UOPF\Exception;
use UOPF\ModelFieldType;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;

final class User extends Model {
    public function renderField(string $field): string {
        switch ($field) {
            case 'username':
            case 'display_name':
                return Utilities::escape($this->data[$field]);

            case 'description':
                return Utilities::wrapParagraphsAround(Utilities::escape($this->data[$field]));

            default:
                $this->throwUnsupportedEditableFieldException();
        }
    }

    public function canBeEditedBy(self $user): bool {
        return $this->is($user);
    }

    public function getMetadata(string $name): mixed {
        return UserMetadataManager::get($name, $this->data['id']);
    }

    public function setMetadata(string $name, mixed $value): void {
        UserMetadataManager::set($name, $value, $this->data['id']);
    }

    public function calculateToken(int $expirationTime, ?string $seed = null): string {
        $password = $this->getMetadata('password');

        if (empty($password))
            throw new Exception('User has no password.');

        $tokenKey = SystemMetadataManager::get('tokenKey');

        if (empty($tokenKey))
            throw new Exception('Missing token key.');

        if (!isset($seed))
            $seed = bin2hex(random_bytes(16));

        $components = [
            static::getTokenAlgorithmVersion(),
            $this->data['id'],
            $password,
            $expirationTime,
            $seed
        ];

        $secret = hash_hmac(
            'sha256',
            implode('|', $components),
            $tokenKey
        );

        return implode('|', [
            $this->data['id'],
            $expirationTime,
            $seed,
            $secret
        ]);
    }

    public function isAdministrator(): bool {
        return $this->data['role'] === 'administrator';
    }

    public function getImageSourceInMetadata(string $name): ?string {
        if (!$id = $this->getMetadata($name))
            return null;

        if (!is_int($id))
            return null;

        if (!$image = ImageManager::fetchEntry($id))
            return null;

        return $image->getSource();
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'role' => ModelFieldType::string,
            'username' => ModelFieldType::string,
            'display_name' => ModelFieldType::string,
            'domain' => ModelFieldType::string,
            'email' => ModelFieldType::string,
            'description' => ModelFieldType::string,
            'registered' => ModelFieldType::time,

            '_followings' => ModelFieldType::integer,
            '_followers'  => ModelFieldType::integer,
            '_posts' => ModelFieldType::integer,
            '_likes' => ModelFieldType::integer,
            '_reposts' => ModelFieldType::integer
        ];
    }

    protected static function getTokenAlgorithmVersion(): int {
        return 1;
    }
}
