<?php

declare(strict_types=1);

namespace Gacela\Container;

use Gacela\Container\Exception\NotFoundException;
use Gacela\Locator\Locator;

final class Container implements ContainerInterface
{
    /** @var mixed[] */
    private array $services = [];

    /** @var mixed[] */
    private array $raw = [];

    public function __construct(array $services = [])
    {
        foreach ($services as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function getLocator(): Locator
    {
        return Locator::getInstance();
    }

    /**
     * @param mixed $service
     */
    public function set(string $id, $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    /**
     * @throws NotFoundException
     *
     * @return mixed
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new NotFoundException(sprintf('The requested service "%s" was not found in the container!', $id));
        }

        if (
            isset($this->raw[$id])
            || !is_object($this->services[$id])
            || !method_exists($this->services[$id], '__invoke')
        ) {
            return $this->services[$id];
        }

        $rawService = $this->services[$id];
        /** @psalm-suppress InvalidFunctionCall */
        $resolvedService = $this->services[$id] = $rawService($this);
        $this->raw[$id] = $rawService;

        return $resolvedService;
    }

    public function remove(string $id): void
    {
        unset($this->services[$id]);
    }
}
