<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Container\ModuleAttributes\Module\Domain;

use Gacela\Container\Attribute\Singleton;

#[Singleton]
final class CounterServiceSingleton
{
    public int $count = 0;

    public function increment(): int
    {
        return ++$this->count;
    }
}
