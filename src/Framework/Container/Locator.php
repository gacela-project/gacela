<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\ClassResolver\AbstractClassResolver;

final class Locator
{
    private const INTERFACE_SUFFIX = 'Interface';

    private static ?Locator $instance = null;

    /** @var array<string, mixed> */
    private array $instanceCache = [];

    public static function getInstance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    private function __construct()
    {
    }

    /**
     * @codeCoverageIgnore
     */
    private function __clone()
    {
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return ?T
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

        $locatedInstance = AbstractClassResolver::getCachedGlobalInstance($concreteClass)
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
     * @return ?T
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
        return false !== mb_strpos($className, self::INTERFACE_SUFFIX);
    }

    private function getConcreteClassFromInterface(string $interface): string
    {
        return str_replace(self::INTERFACE_SUFFIX, '', $interface);
    }
}
