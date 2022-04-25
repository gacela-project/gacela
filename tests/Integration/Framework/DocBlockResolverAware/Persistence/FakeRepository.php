<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolverAware\Persistence;

final class FakeRepository
{
    public function findName(): string
    {
        return 'name';
    }
}
