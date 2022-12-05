<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;

final class Locator
{
    private static ?Locator $instance = null;

    /** @var array<string, mixed> */
    private array $instanceCache = [];

    private function __construct()
    {
    }

    /**
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public function get(string $className)
    {
        if (isset($this->instanceCache[$className])) {
            /** @var T $instance */
            $instance = $this->instanceCache[$className];

            return $instance;
        }

        $locatedInstance = AnonymousGlobal::getByClassName($className)
            ?? $this->newInstance($className);

        /** @psalm-suppress MixedAssignment */
        $this->instanceCache[$className] = $locatedInstance;

        return $locatedInstance;
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    private function newInstance(string $className)
    {
        if (class_exists($className)) {
            /** @psalm-suppress MixedMethodCall */
            return new $className();
        }

        return null;
    }
}
