<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

use Gacela\Framework\AbstractFactory;

final class ProvidedFactory extends AbstractFactory
{
    public function knownCallOnAClassStringKey(): string
    {
        return $this->getProvidedDependency(ProvidedClock::class)->now();
    }

    public function typoOnAClassStringKey(): string
    {
        return $this->getProvidedDependency(ProvidedClock::class)->nooow();
    }

    public function stringKeyStaysMixed(): mixed
    {
        return $this->getProvidedDependency('some.service');
    }
}
