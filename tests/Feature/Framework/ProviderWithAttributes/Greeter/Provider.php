<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Framework\ProviderWithAttributes\External\Clock;

final class Provider extends AbstractProvider
{
    public const CLOCK = 'GREETER_CLOCK';

    public const PREFIXES = 'GREETER_PREFIXES';

    public const LOCATOR_CHECK = 'GREETER_LOCATOR_CHECK';

    #[Provides(self::CLOCK)]
    public function clock(): Clock
    {
        return new Clock();
    }

    /**
     * @return list<string>
     */
    #[Provides(self::PREFIXES)]
    public function prefixes(): array
    {
        return ['Hello', 'Hola', 'Bonjour'];
    }

    #[Provides(self::LOCATOR_CHECK)]
    public function locatorCheck(Container $container): string
    {
        return $container::class;
    }
}
