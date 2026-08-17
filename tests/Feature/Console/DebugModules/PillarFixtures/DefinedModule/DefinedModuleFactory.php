<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\PillarFixtures\DefinedModule;

use Gacela\Framework\AbstractFactory;

final class DefinedModuleFactory extends AbstractFactory
{
    public function __construct(
        public readonly DefinedContract $contract,
    ) {
    }
}
