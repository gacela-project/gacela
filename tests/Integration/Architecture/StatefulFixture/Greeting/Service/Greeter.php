<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Greeting\Service;

use function sprintf;

final class Greeter
{
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function greet(string $name): string
    {
        return sprintf('%s, %s.', $this->prefix, $name);
    }
}
