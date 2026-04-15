<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute\Providers;

final class CallCounter
{
    public int $count = 0;

    public function bump(): int
    {
        return ++$this->count;
    }
}
