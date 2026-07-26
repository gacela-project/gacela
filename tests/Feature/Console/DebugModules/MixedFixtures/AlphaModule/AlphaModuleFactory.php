<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\MixedFixtures\AlphaModule;

use Gacela\Framework\AbstractFactory;

/**
 * Half resolvable, half not, so the per-parameter listing has something to
 * hide without --detail and something to add with it.
 */
final class AlphaModuleFactory extends AbstractFactory
{
    public function __construct(
        public readonly AlphaCollaborator $collaborator,
        public readonly string $mandatory,
        public readonly int $count,
    ) {
    }
}
