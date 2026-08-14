<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * Two methods declaring one id. `ProvidesScanner::scan()` `set()`s them in
 * order, so `late()` answers and `early()` never runs.
 */
final class RepeatedIdProvider extends AbstractProvider
{
    public const THING = 'THE_THING';

    public const OTHER = 'THE_OTHER';

    public function provideModuleDependencies(Container $container): void
    {
    }

    /**
     * Declared first on purpose. It is the id with nothing wrong with it, so
     * the check skips it -- and a skip that is reached last is a `continue`
     * a `break` mutant can replace without failing anything.
     */
    #[Provides(self::OTHER)]
    public function other(): string
    {
        return 'other';
    }

    #[Provides(self::THING)]
    public function early(): string
    {
        return 'early';
    }

    #[Provides(self::THING)]
    public function late(): string
    {
        return 'late';
    }
}
