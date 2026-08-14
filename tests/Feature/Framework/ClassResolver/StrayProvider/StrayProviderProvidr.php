<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\StrayProvider;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Misspelled on purpose: this is the file the resolver cannot find and the
 * reader already wrote. Renaming it to `StrayProviderProvider` is what the
 * hint under the candidate list tells them to do, so it must stay misspelled.
 */
final class StrayProviderProvidr extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
