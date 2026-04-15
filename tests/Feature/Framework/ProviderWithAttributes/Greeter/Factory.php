<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ProviderWithAttributes\External\Clock;

/**
 * @method Provider getProvider()
 */
final class Factory extends AbstractFactory
{
    public function createGreetingService(): GreetingService
    {
        return new GreetingService(
            $this->getClock(),
            $this->createPrefixes(),
        );
    }

    /**
     * @return list<string>
     */
    public function createPrefixes(): array
    {
        /** @var list<string> $prefixes */
        $prefixes = $this->getProvidedDependency(Provider::PREFIXES);
        return $prefixes;
    }

    private function getClock(): Clock
    {
        /** @var Clock $clock */
        $clock = $this->getProvidedDependency(Provider::CLOCK);
        return $clock;
    }
}
