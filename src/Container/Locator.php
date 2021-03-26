<?php

declare(strict_types=1);

namespace Gacela\Container;

final class Locator
{
    private static ?Locator $instance = null;

    /** @var mixed[] */
    private static array $instanceCache = [];

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

    private function __clone()
    {
    }

    /**
     * @return mixed
     */
    public function get(string $id)
    {
        if (isset(static::$instanceCache[$id])) {
            return static::$instanceCache[$id];
        }

        $newInstance = $this->newInstance($id);
        static::$instanceCache[$id] = $newInstance;

        return $newInstance;
    }

    /**
     * @return mixed
     */
    private function newInstance(string $className)
    {
        if (!class_exists($className)) {
            return $className;
        }

        return new $className();
    }
}
