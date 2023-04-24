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

        /** @var T|null $locatedInstance */
        $locatedInstance = AnonymousGlobal::getByClassName($className)
            ?? GacelaContainer::create($className);

        /** @psalm-suppress MixedAssignment */
        $this->instanceCache[$className] = $locatedInstance;

        return $locatedInstance;
    }
}
