<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Infrastructure\Persistence;

interface RepositoryInterface
{
    public function findExampleQuery(): string;
}
