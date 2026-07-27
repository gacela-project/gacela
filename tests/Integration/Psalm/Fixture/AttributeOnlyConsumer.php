<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Declares its pillar with the attribute and **no** `@method` docblock, so the
 * only thing that can type `getFacade()` for Psalm is the plugin.
 */
#[ServiceMap(method: 'getFacade', className: ConsumerFacade::class)]
final class AttributeOnlyConsumer
{
    use ServiceResolverAwareTrait;

    public function callsAKnownMethod(): string
    {
        return $this->getFacade()->knownMethod();
    }

    public function callsAMethodThatDoesNotExist(): string
    {
        return $this->getFacade()->typoMethod();
    }
}
