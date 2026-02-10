<?php

declare(strict_types=1);

namespace Gacela\Framework;

/**
 * Interface for accessing dependencies provided by module providers.
 */
interface ProviderAccessorInterface
{
    /**
     * Retrieve a dependency that was provided by a module provider.
     *
     * @param string $key The dependency key
     *
     * @return mixed The dependency value
     */
    public function getProvidedDependency(string $key): mixed;
}
