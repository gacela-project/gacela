<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\src\Main\ModuleC;

use GacelaTest\Feature\Framework\ResolveDifferentProjectNamespaces\vendor\ThirdParty\ModuleC\Factory as ThirdPartyFactory;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use Override;

/**
 * Named with the module prefix, which is the shape `docs/getting-a-dependency.md`
 * teaches for per-entrypoint wiring -- its sibling overrides in `ModuleA` and
 * `ModuleB` use the bare `Factory` name, so without this the documented spelling
 * is the one nothing exercises.
 */
final class ModuleCFactory extends ThirdPartyFactory
{
    #[Override]
    public function createStringC1(): StringValueInterface
    {
        return new StringValue('Overridden, from src\Main\ModuleC::StringC1');
    }
}
