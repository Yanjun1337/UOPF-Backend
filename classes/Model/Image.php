<?php
declare(strict_types=1);
namespace UOPF\Model;

use UOPF\Model;
use UOPF\ModelFieldType;
use UOPF\Facade\Manager\Metadata\System as SystemMetadataManager;
use const UOPF\ROOT;

final class Image extends Model {
    public function getPath(): string {
        return static::getImagesDirectoryPath() . $this->getFileName();
    }

    public function getSource(): string {
        return static::getImagesDirectorySource() . $this->getFileName();
    }

    public function getFileName(): string {
        $extension = $this->data['metadata']['extension'];
        return "{$this->data['file']}.{$extension}";
    }

    public static function getImagesDirectoryPath(): string {
        if (is_dir(ROOT . 'variable/images/'))
            return ROOT . 'variable/images/';
        else
            return '/var/lib/uopf/images/';
    }

    public static function getImagesDirectorySource(): string {
        $source = SystemMetadataManager::get('frontendAddress');

        if (is_dir(ROOT . 'variable/images/'))
            $source .= 'variable/images/';
        else
            $source .= 'assets/images/';

        return $source;
    }

    public static function getSchema(): array {
        return [
            'id' => ModelFieldType::integer,
            'status' => ModelFieldType::string,
            'file' => ModelFieldType::string,
            'created' => ModelFieldType::time,
            'modified' => ModelFieldType::time,
            'user' => ModelFieldType::integer,
            'record' => ModelFieldType::integer,
            'position' => ModelFieldType::integer,
            'metadata' => ModelFieldType::serialized
        ];
    }
}
