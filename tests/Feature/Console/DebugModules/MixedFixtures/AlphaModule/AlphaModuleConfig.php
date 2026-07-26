<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule;

use Gacela\Framework\AbstractConfig;

/**
 * Declares a constructor that takes nothing, which the report distinguishes
 * from having no constructor at all.
 */
final class AlphaModuleConfig extends AbstractConfig
{
    public function __construct()
    {
    }
}
