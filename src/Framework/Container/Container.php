<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Gacela\Framework\Container\Exception\ContainerException;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use SplObjectStorage;

use function is_object;

final class Container implements ContainerInterface
{
    /** @var array<string,mixed> */
    private array $raw = [];

    /** @var array<string,mixed> */
    private array $services = [];

    private SplObjectStorage $factoryServices;

    public function __construct()
    {
        $this->factoryServices = new SplObjectStorage();
    }

    public function getLocator(): Locator
    {
        return Locator::getInstance();
    }

    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * @throws ContainerKeyNotFoundException
     *
     * @return mixed
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new ContainerKeyNotFoundException($this, $id);
        }

        if (
            isset($this->raw[$id])
            || !is_object($this->services[$id])
            || !method_exists($this->services[$id], '__invoke')
        ) {
            return $this->services[$id];
        }

        if (isset($this->factoryServices[$this->services[$id]])) {
            return $this->services[$id]($this);
        }

        $rawService = $this->services[$id];

        /** @psalm-suppress InvalidFunctionCall */
        $this->services[$id] = $rawService($this);

        /** @var mixed $resolvedService */
        $resolvedService = $this->services[$id];
        $this->raw[$id] = $rawService;

        return $resolvedService;
    }

    public function factory(object $service): object
    {
        if (!method_exists($service, '__invoke')) {
            throw ContainerException::serviceNotInvokable();
        }

        $this->factoryServices->attach($service);

        return $service;
    }

    public function remove(string $id): void
    {
        unset(
            $this->raw[$id],
            $this->services[$id]
        );
    }
}
