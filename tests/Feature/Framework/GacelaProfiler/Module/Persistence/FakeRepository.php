<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\GacelaProfiler\Module\Persistence;

final class FakeRepository
{
    public function findName(): string
    {
        return 'name';
    }
}
