<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Broken;

use Error;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class BrokenProvider extends AbstractProvider
{
    public function __construct()
    {
        // Stands in for a module whose provider is not constructible during warm
        // (e.g. a TypeError from a real constructor): a PHP Error, not an Exception.
        throw new Error('cannot construct BrokenProvider during warm');
    }

    public function provideModuleDependencies(Container $container): void
    {
    }
}
