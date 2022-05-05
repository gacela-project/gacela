<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\UsingGacelaConfigFn\LocalConfig\Domain\Greeter;

final class CustomNameGenerator
{
    public function getNames(): string
    {
        return 'Chemaclass & Jesus';
    }
}
