<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock;

use DateTimeImmutable;

final class SystemClock implements ClockInterface
{
    public function today(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d');
    }
}
