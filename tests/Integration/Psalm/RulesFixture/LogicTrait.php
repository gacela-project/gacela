<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

trait LogicTrait
{
    public function fromTheTrait(): int
    {
        return 1 + 1;
    }
}
