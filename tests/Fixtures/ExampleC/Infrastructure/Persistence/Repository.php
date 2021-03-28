<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Infrastructure\Persistence;

use Gacela\AbstractRepository;

final class Repository extends AbstractRepository implements RepositoryInterface
{
    public function findExampleQuery(): string
    {
        return 'result from a repository';
    }
}
