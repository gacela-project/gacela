<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter;

use GacelaTest\Feature\Framework\ProviderWithAttributes\External\Clock;

use function sprintf;

final class GreetingService
{
    /**
     * @param list<string> $prefixes
     */
    public function __construct(
        private readonly Clock $clock,
        private readonly array $prefixes,
    ) {
    }

    public function greet(string $name): string
    {
        return sprintf('%s %s (%s)', $this->prefixes[0], $name, $this->clock->now());
    }
}
