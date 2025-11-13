<?php
declare(strict_types=1);
namespace UOPF\Setting\General\Group;

use UOPF\Setting\Group;

/**
 * Setting Group
 */
final class Addresses extends Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title = 'URLs';

    /**
     * The description of the setting group.
     */
    protected(set) string $description = 'Be careful. An incorrect value may result in a connection loss.';

    /**
     * The registered setting fields in the setting group.
     */
    protected(set) array $fields = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $namespace = explode('\\', static::class);
        array_pop($namespace);
        array_pop($namespace);

        $namespace[] = 'Field';
        $namespace = implode('\\', $namespace);

        foreach ($this->getFields() as $name) {
            $class = "{$namespace}\\{$name}";
            $this->register(new $class());
        }
    }

    /**
     * Returns the classes of the registered setting fields.
     */
    public function getFields(): array {
        return [
            'FrontendAddress'
        ];
    }
}
