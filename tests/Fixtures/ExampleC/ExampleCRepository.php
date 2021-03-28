<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractRepository;

final class ExampleCRepository extends AbstractRepository implements ExampleCRepositoryInterface
{
    public function findExampleQuery(): string
    {
        return 'result from a repository';
    }
}
