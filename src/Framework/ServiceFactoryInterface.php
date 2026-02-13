<?php

declare(strict_types=1);

namespace Gacela\Framework;

/**
 * Interface for creating and managing service instances.
 */
interface ServiceFactoryInterface
{
    /**
     * Create or retrieve a singleton instance of a service.
     *
     * @template TService
     *
     * @param string $key Unique identifier for the service instance
     * @param callable(): TService $creator Factory callable that creates the service
     *
     * @return TService The service instance
     */
    public function singleton(string $key, callable $creator): mixed;
}
