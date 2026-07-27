<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Greeting\Infrastructure;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Architecture\StatefulFixture\Greeting\GreetingFacade;

/**
 * Resolves its pillar through the trait, so running it populates the
 * ServiceResolverAwareTrait statics.
 *
 * Declared with #[ServiceMap] rather than a `@method` docblock on purpose: the
 * docblock fallback emits a deprecation that DocBlockResolver::$warned
 * deduplicates process-wide, so using it here would make this test's result
 * depend on whether DocBlockFallbackDeprecationTest ran first.
 */
#[ServiceMap(method: 'getGreetingFacade', className: GreetingFacade::class)]
final class GreetingCommand
{
    use ServiceResolverAwareTrait;

    public function run(string $name): string
    {
        return $this->getGreetingFacade()->cachedGreet($name);
    }
}
