<?php
declare(strict_types=1);
namespace UOPF\Setting;

/**
 * Settings Manager
 */
final class Settings {
    /**
     * The registered setting pages.
     */
    protected(set) array $pages = [];

    /**
     * The instance.
     */
    protected static self $instance;

    /**
     * Constructor.
     */
    public function __construct() {
        foreach ($this->getPages() as $name) {
            $class = "\\UOPF\\Setting\\{$name}\\{$name}";
            $this->register(new $class());
        }
    }

    /**
     * Registers a setting page.
     */
    public function register(Page $page): void {
        $this->pages[$page->name] = $page;
    }

    /**
     * Returns the classes of the registered setting pages.
     */
    public function getPages(): array {
        return [
            'General',
            'StaticPage'
        ];
    }

    /**
     * Returns the instance.
     */
    public static function getInstance(): self {
        if (!isset(static::$instance))
            static::$instance = new static();

        return static::$instance;
    }
}
