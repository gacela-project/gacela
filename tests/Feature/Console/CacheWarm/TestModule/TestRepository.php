<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\CacheWarm\TestModule;

final class TestRepository
{
    public function findName(): string
    {
        return 'test';
    }
}
