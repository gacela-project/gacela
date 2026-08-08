<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

use Gacela\Framework\AbstractFactory;

/**
 * Kept apart from `ProvidedFactory` so the two can be asserted on separately:
 * nothing in the type system says what a string key resolves to, so this call
 * stays `mixed` and Psalm says so.
 *
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class StringKeyFactory extends AbstractFactory
{
    public function callsThroughAStringKey(): int
    {
        return $this->getProvidedDependency('some.service')->now();
    }
}
