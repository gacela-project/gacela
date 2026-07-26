<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\MixedFixtures\BetaModule;

use Gacela\Framework\AbstractConfig;

/**
 * The module deliberately has no Factory, so a Config sitting behind the
 * missing pillar still has to be inspected.
 */
final class BetaModuleConfig extends AbstractConfig
{
    public function __construct(
        public readonly BetaCollaborator $collaborator,
    ) {
    }
}
