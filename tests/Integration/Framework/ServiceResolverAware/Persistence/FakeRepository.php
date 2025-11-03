<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolverAware\Persistence;

final class FakeRepository
{
    public function findName(): string
    {
        return 'name';
    }
}
