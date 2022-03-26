<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;

final class Locator
{
    private const INTERFACE_SUFFIX = 'Interface';

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
        /** @var class-string<T> $concreteClass */
        $concreteClass = $this->getConcreteClass($className);

        if (isset($this->instanceCache[$concreteClass])) {
            /** @var T $instance */
            $instance = $this->instanceCache[$concreteClass];

            return $instance;
        }

        $locatedInstance = AnonymousGlobal::getByClassName($concreteClass)
            ?? $this->newInstance($concreteClass);

        /** @psalm-suppress MixedAssignment */
        $this->instanceCache[$concreteClass] = $locatedInstance;

        return $locatedInstance;
    }

    private function getConcreteClass(string $className): string
    {
        if ($this->isInterface($className)) {
            return $this->getConcreteClassFromInterface($className);
        }

        return $className;
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

    private function isInterface(string $className): bool
    {
        return mb_strpos($className, self::INTERFACE_SUFFIX) !== false;
    }

    private function getConcreteClassFromInterface(string $interface): string
    {
        return str_replace(self::INTERFACE_SUFFIX, '', $interface);
    }
}
