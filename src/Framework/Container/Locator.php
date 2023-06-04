<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;

/**
 * @internal
 */
final class Locator implements LocatorInterface
{
    private static ?Locator $instance = null;

    /** @var array<string, mixed> */
    private array $instanceCache = [];

    private ContainerInterface $container;

    private function __construct(
        ?ContainerInterface $container = null,
    ) {
        $this->container = $container ?? new Container();
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

    /**
     * @template T
     *
     * @param class-string<T> $key
     * @param T $value
     */
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
    public static function getSingleton(string $className, ?Container $container = null)
    {
        return self::getInstance($container)->get($className);
    }

    public static function getInstance(?Container $container = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($container);
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
            ?? $this->container->get($className);

        $this->add($className, $locatedInstance);

        return $locatedInstance;
    }

    /**
     * @template T
     *
     * @param class-string<T> $key
     * @param T|null $value
     */
    private function add(string $key, mixed $value = null): self
    {
        $this->instanceCache[$key] = $value;

        return $this;
    }
}
