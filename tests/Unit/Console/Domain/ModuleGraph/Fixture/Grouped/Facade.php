<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\Grouped;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Unit\Console\Domain\ModuleGraph\Fixture\{
    Alpha\Facade as AlphaFacade,
    Zebra\Facade as ZebraFacade,
};

/**
 * A grouped import spanning two modules, split across lines and aliased --
 * the shape the previous regex reduced to the bare prefix `...\Fixture\`,
 * producing no edge at all.
 *
 * `use function` and `use const` are covered in PhpImportParserTest rather than
 * here: cs-fixer strips an unused import, so one cannot survive in a fixture.
 */
final class Facade extends AbstractFacade
{
    public function names(): string
    {
        return AlphaFacade::class . ZebraFacade::class;
    }
}
