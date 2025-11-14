<?php
declare(strict_types=1);
namespace UOPF\Setting;

/**
 * Setting Group
 */
abstract class Group {
    /**
     * The title of the setting group.
     */
    protected(set) string $title;

    /**
     * The description of the setting group.
     */
    protected(set) string $description;

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
    abstract public function getFields(): array;

    /**
     * Registers a setting field.
     */
    public function register(Field $field): void {
        $this->fields[$field->name] = $field;
    }

    /**
     * Sets all fields in the setting group.
     */
    public function set(array $filtered): void {
        foreach ($filtered as $name => $value)
            if (isset($this->fields[$name]))
                $this->fields[$name]->set($value);
    }

    /**
     * Fill the default values of the registered setting fields in the setting group.
     */
    public function fillDefaults(): void {
        foreach ($this->fields as $field)
            $field->fillDefault();
    }

    /**
     * Returns the schema of the setting group.
     */
    public function getSchema(): array {
        $schema = [
            'title' => $this->title
        ];

        if (isset($this->description))
            $schema['description'] = $this->description;

        $fields = [];

        foreach ($this->fields as $field)
            $fields[$field->name] = $field->getSchema();

        $schema['fields'] = $fields;
        return $schema;
    }
}
