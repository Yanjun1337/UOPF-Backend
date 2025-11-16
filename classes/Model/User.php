<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\Utilities;
use UOPF\Exception;
use UOPF\ModelFieldType;
use UOPF\DatabaseLockType;
use UOPF\Facade\Database;
use UOPF\Facade\Manager\User as UserManager;
use UOPF\Facade\Manager\Image as ImageManager;
use UOPF\Facade\Manager\Metadata\User as UserMetadataManager;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

use const JSON_ERROR_NONE;

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

    public function getCompletedUserGuidances(): array {
        return $this->getMetadata('understood') ?? [];
    }

    public function isBlocked(): bool {
        return $this->hasPunishment('blocked');
    }

    public function isDeactivated(): bool {
        return $this->hasPunishment('deactivated');
    }

    protected function hasPunishment(string $type): bool {
        if ($punishment = $this->getMetadata($type))
            return $punishment['closing'] >= time();
        else
            return false;
    }

    public function refreshLastLogin(?string $address): void {
        $lastLogin = [
            'time' => time()
        ];

        if (isset($address)) {
            $lastLogin['address'] = $address;

            if (($location = static::getAddressLocation($address)) !== null)
                $lastLogin['location'] = $location;
        } else {
            $lastLogin['address'] = 'Unknown';
        }

        Database::transaction(function () use (&$lastLogin) {
            if ($locked = UserManager::fetchEntryDirectly($this->data['id'], lock: DatabaseLockType::read))
                $locked->setMetadata('lastLogin', $lastLogin);
        });
    }

    protected static function getAddressLocation(string $address): ?string {
        $client = new Client([
            'base_uri' => 'https://api.ip.sb',
            'timeout'  => 3.0
        ]);

        try {
            $response = $client->request('GET', "/geoip/{$address}");
        } catch (GuzzleException) {
            return null;
        }

        $body = strval($response->getBody());
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data))
            return null;

        $components = [];

        if (isset($data['city']))
            $components[] = $data['city'];

        if (isset($data['region']))
            $components[] = $data['region'];

        if (isset($data['country']))
            $components[] = $data['country'];

        if (empty($components))
            return null;
        else
            return implode(', ', $components);
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
