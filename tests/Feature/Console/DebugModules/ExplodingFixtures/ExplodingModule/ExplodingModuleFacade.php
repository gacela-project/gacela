<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\ExplodingFixtures\ExplodingModule;

use Gacela\Framework\AbstractFacade;

/**
 * Never loaded by any other test, so a test can register an autoloader that
 * throws for this exact class and watch module discovery fail.
 */
final class ExplodingModuleFacade extends AbstractFacade
{
}
