<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\BrokenFixtures\BrokenModule;

use Gacela\Framework\AbstractFactory;

final class BrokenModuleFactory extends AbstractFactory
{
    public function __construct(
        public readonly UnboundDependency $dependency,
    ) {
    }
}
