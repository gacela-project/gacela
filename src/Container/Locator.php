<?php

declare(strict_types=1);

namespace Gacela\Container;

final class Locator
{
    private static ?Locator $instance = null;

    /** @var array<string,mixed> */
    public static array $instanceCache = [];

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * @return mixed|null
     */
    public function get(string $className)
    {
        if (isset(self::$instanceCache[$className])) {
            return self::$instanceCache[$className];
        }

        if (!class_exists($className)) {
            return null;
        }

        $instance = new $className();
        self::$instanceCache[$className] = $instance;

        return $instance;
    }
}
