<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Resolves a pillar through `ServiceResolverAwareTrait` while declaring neither
 * `#[ServiceMap]` nor a `@method` docblock.
 *
 * 1.x shipped an `ignoreErrors` entry in `phpstan-gacela.neon` that silenced
 * this. 2.0 removes it, so the call is now reported -- which is the point: a
 * suppressed call is not a typed one. It degraded to `mixed`, and everything
 * reached *through* the accessor went unchecked along with it.
 */
final class UndeclaredConsumer
{
    use ServiceResolverAwareTrait;

    public function resolvesAnUndeclaredPillar(): mixed
    {
        return $this->getFacade();
    }
}
