<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Greeting;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;

/**
 * Declared with #[Provides] rather than provideModuleDependencies(), so
 * resolving this module populates ProvidesScanner::$cache too.
 */
final class GreetingProvider extends AbstractProvider
{
    public const PREFIX = 'GREETING_PREFIX';

    #[Provides(self::PREFIX)]
    public function prefix(): string
    {
        return 'Hello';
    }
}
