<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

use Gacela\Framework\AbstractFactory;

/**
 * Asks for its dependency by class-string, with no `@var` in sight. Without the
 * plugin both calls below are made on `mixed`, which is not a checked call --
 * the typo would pass silently.
 *
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class ProvidedFactory extends AbstractFactory
{
    public function callsAKnownMethod(): int
    {
        return $this->getProvidedDependency(ProvidedClock::class)->now();
    }

    public function callsAMethodThatDoesNotExist(): int
    {
        return $this->getProvidedDependency(ProvidedClock::class)->typoOnTheClock();
    }
}
