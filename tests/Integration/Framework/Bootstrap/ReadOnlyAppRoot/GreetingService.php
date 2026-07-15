<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ReadOnlyAppRoot;

final class GreetingService
{
    public function greet(): string
    {
        return 'greetings-from-read-only-root';
    }
}
