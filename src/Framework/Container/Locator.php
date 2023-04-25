<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Container\Container as GacelaContainer;
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

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public static function addSingleton(string $key, mixed $value): void
    {
        self::getInstance()->add($key, $value);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public static function getSingleton(string $className)
    {
        return self::getInstance()->get($className);
    }

    public function add(string $key, mixed $value): self
    {
        $this->instanceCache[$key] = $value;

        return $this;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
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

        /** @var T|null $locatedInstance */
        $locatedInstance = AnonymousGlobal::getByClassName($className)
            ?? GacelaContainer::create($className);

        /** @psalm-suppress MixedAssignment */
        $this->instanceCache[$className] = $locatedInstance;

        return $locatedInstance;
    }
}
