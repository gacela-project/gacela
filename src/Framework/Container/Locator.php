<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Exception\ServiceNotFoundException;

/**
 * @internal
 */
final class Locator implements LocatorInterface
{
    private static ?Locator $instance = null;

    /** @var array<string, mixed> */
    private array $instanceCache = [];

    private function __construct(
        private readonly ContainerInterface $container = new Container(),
    ) {
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

    /**
     * Get a singleton from the container, throwing an exception if not found.
     * Use this when you expect the service to exist and want type-safe returns.
     *
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @throws ServiceNotFoundException
     *
     * @return T
     */
    public static function getRequiredSingleton(string $className, ?Container $container = null): object
    {
        return self::getInstance($container)->getRequired($className);
    }

    public static function getInstance(?Container $container = null): self
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self($container ?? new Container());
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
     * @template T of object
     *
     * @param class-string<T> $className
     *
     * @throws ServiceNotFoundException
     *
     * @return T
     */
    public function getRequired(string $className): object
    {
        $instance = $this->get($className);

        if ($instance === null) {
            throw new ServiceNotFoundException($className);
        }

        return $instance;
    }

    /**
     * @template T
     *
     * @param class-string<T> $key
     * @param T|null $value
     */
    private function add(string $key, mixed $value = null): void
    {
        $this->instanceCache[$key] = $value;
    }
}
