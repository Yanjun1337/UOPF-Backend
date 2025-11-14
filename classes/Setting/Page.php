<?php
declare(strict_types=1);
namespace UOPF\Setting;

use UOPF\Facade\Database;
use UOPF\Validator\DictionaryValidator;
use UOPF\Validator\DictionaryValidatorElement;

/**
 * Setting Page
 */
abstract class Page {
    /**
     * The name of the setting page.
     */
    protected(set) string $name;

    /**
     * The title of the setting page.
     */
    protected(set) string $title;

    /**
     * The label of the setting page in the menu.
     */
    protected(set) string $menuLabel;

    /**
     * The description of the setting page.
     */
    protected(set) string $description;

    /**
     * The registered setting groups in the setting page.
     */
    protected(set) array $groups = [];

    /**
     * Constructor.
     */
    public function __construct() {
        $namespace = explode('\\', static::class);
        array_pop($namespace);

        $namespace[] = 'Group';
        $namespace = implode('\\', $namespace);

        foreach ($this->getGroups() as $name) {
            $class = "{$namespace}\\{$name}";
            $this->register(new $class());
        }
    }

    /**
     * Returns the classes of the registered setting groups.
     */
    abstract public function getGroups(): array;

    /**
     * Registers a setting group.
     */
    public function register(Group $group): void {
        $this->groups[] = $group;
    }

    /**
     * Sets all fields in the setting page.
     */
    public function set(mixed $data): void {
        $elements = [];

        foreach ($this->groups as $group) {
            foreach ($group->fields as $field) {
                $elements[$field->name] = new DictionaryValidatorElement(
                    label: $field->label,
                    validator: $field->getValidator()
                );
            }
        }

        $filtered = (new DictionaryValidator($elements))->filter($data);

        Database::transaction(function () use (&$filtered) {
            foreach ($this->groups as $group)
                $group->set($filtered);
        });
    }

    /**
     * Fill the default values of the setting fields in the setting page.
     */
    public function fillDefaults(): void {
        foreach ($this->groups as $group)
            $group->fillDefaults();
    }

    /**
     * Returns the schema of the setting page.
     */
    public function getSchema(): array {
        $schema = [
            'title' => $this->title
        ];

        if (isset($this->description))
            $schema['description'] = $this->description;

        $groups = [];

        foreach ($this->groups as $group)
            $groups[] = $group->getSchema();

        $schema['groups'] = $groups;
        return $schema;
    }
}
