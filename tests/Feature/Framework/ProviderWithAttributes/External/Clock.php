<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes\External;

final class Clock
{
    public function now(): string
    {
        return '2026-04-15';
    }
}
