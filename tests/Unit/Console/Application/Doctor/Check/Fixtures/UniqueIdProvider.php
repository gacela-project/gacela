<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

final class UniqueIdProvider extends AbstractProvider
{
    public const FIRST = 'FIRST_ID';

    public const SECOND = 'SECOND_ID';

    public function provideModuleDependencies(Container $container): void
    {
    }

    #[Provides(self::FIRST)]
    public function first(): string
    {
        return 'first';
    }

    #[Provides(self::SECOND)]
    public function second(): string
    {
        return 'second';
    }
}
