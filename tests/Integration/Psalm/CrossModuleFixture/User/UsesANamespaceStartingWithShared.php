<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\SharedFoo\Thing;

final class UsesANamespaceStartingWithShared
{
    public function __construct(
        private readonly Thing $thing,
    ) {
    }

    public function build(): string
    {
        return $this->thing->run();
    }
}
