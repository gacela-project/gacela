<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

final class ChildRelativeProvider extends BaseRelativeProvider
{
    #[Provides('FROM_PARENT')]
    public function fromParent(): parent
    {
        return $this;
    }
}
