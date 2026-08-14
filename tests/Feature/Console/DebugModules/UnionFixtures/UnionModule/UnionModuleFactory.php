<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModules\UnionFixtures\UnionModule;

use Gacela\Framework\AbstractFactory;

/**
 * A union-typed constructor parameter, which `ConstructorInspector` does not
 * walk. It is reported as unresolvable because nothing here has an opinion
 * about it -- and `--check` must not fail a build over a gap in the tool.
 */
final class UnionModuleFactory extends AbstractFactory
{
    public function __construct(
        public readonly int|string $eitherWay,
    ) {
    }
}
