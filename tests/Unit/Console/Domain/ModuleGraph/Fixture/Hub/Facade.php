<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\Hub;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\AlphaExtra\Facade as AlphaExtraFacade;
use GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\Zebra\Facade as ZebraFacade;

/**
 * Imports Zebra before AlphaExtra so the builder has to sort, and imports
 * `...\AlphaExtra\...` while a separate `...\Alpha` module exists, so a prefix
 * match without the namespace separator would invent a dependency on Alpha.
 */
final class Facade extends AbstractFacade
{
    public function names(): string
    {
        return ZebraFacade::class . AlphaExtraFacade::class;
    }
}
