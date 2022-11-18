<?php

declare(strict_types=1);

namespace Gacela\Framework\Container;

use Closure;
use Gacela\Framework\Container\Exception\ContainerException;
use Gacela\Framework\Container\Exception\ContainerKeyNotFoundException;
use SplObjectStorage;

use function count;
use function is_callable;
use function is_object;

final class Container implements ContainerInterface
{
    /** @var array<string,mixed> */
    private array $services = [];

    private SplObjectStorage $factoryServices;

    private SplObjectStorage $protectedServices;

    /** @var array<string,list<Closure>> */
    private array $servicesToExtend;

    /** @var array<string,bool> */
    private array $frozenServices = [];

    private ?string $currentlyExtending = null;

    /**
     * @param array<string,list<Closure>> $servicesToExtend
     */
    public function __construct(array $servicesToExtend = [])
    {
        $this->servicesToExtend = $servicesToExtend;
        $this->factoryServices = new SplObjectStorage();
        $this->protectedServices = new SplObjectStorage();
    }

    public function getLocator(): Locator
    {
        return Locator::getInstance();
    }

    public function set(string $id, $service): void
    {
        if (isset($this->frozenServices[$id])) {
            throw ContainerException::serviceFrozen($id);
        }

        $this->services[$id] = $service;

        if ($this->currentlyExtending === $id) {
            return;
        }

        $this->extendService($id);
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

        $this->frozenServices[$id] = true;

        if (!is_object($this->services[$id])
            || isset($this->protectedServices[$this->services[$id]])
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
            $this->services[$id],
            $this->frozenServices[$id],
        );
    }

    /**
     * @psalm-suppress MixedAssignment
     */
    public function extend(string $id, Closure $service): Closure
    {
        if (!$this->has($id)) {
            $this->extendLater($id, $service);

            return $service;
        }

        if (isset($this->frozenServices[$id])) {
            throw ContainerException::serviceFrozen($id);
        }

        $factory = $this->services[$id];
        $extended = $this->generateExtendedService($service, $factory);
        $this->set($id, $extended);

        return $extended;
    }

    public function protect($service)
    {
        $this->protectedServices->attach($service);

        return $service;
    }

    private function extendLater(string $id, Closure $service): void
    {
        if (!isset($this->servicesToExtend[$id])) {
            $this->servicesToExtend[$id] = [];
        }

        $this->servicesToExtend[$id][] = $service;
    }

    /**
     * @psalm-suppress MissingClosureReturnType,MixedAssignment
     *
     * @param mixed $factory
     */
    private function generateExtendedService(Closure $service, $factory): Closure
    {
        if (is_callable($factory)) {
            return static function (self $container) use ($service, $factory) {
                $r1 = $factory($container);
                $r2 = $service($r1, $container);

                return $r2 ?? $r1;
            };
        }

        if (is_object($factory)) {
            return static function (self $container) use ($service, $factory) {
                $r = $service($factory, $container);

                return $r ?? $factory;
            };
        }

        throw ContainerException::serviceNotExtendable();
    }

    private function extendService(string $id): void
    {
        if (!isset($this->servicesToExtend[$id]) || count($this->servicesToExtend[$id]) === 0) {
            return;
        }
        $this->currentlyExtending = $id;

        foreach ($this->servicesToExtend[$id] as $service) {
            $extended = $this->extend($id, $service);
        }

        unset($this->servicesToExtend[$id]);
        $this->currentlyExtending = null;

        $this->set($id, $extended);
    }
}
