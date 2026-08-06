<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\MissingFile\MissingContainerServiceKey;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Empty on purpose, and the emptiness is the whole fixture.
 *
 * `getProvidedDependency()` raises `ProviderNotFoundException` when a module has
 * no Provider at all — that path is covered by the `MissingProviderFile` sibling.
 * This module needs a Provider that exists and registers *nothing*, so the call
 * falls through to the container and returns null instead.
 *
 * Filling this method in, or deleting the class as dead weight, turns the test
 * into a duplicate of the sibling without failing.
 */
final class Provider extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
