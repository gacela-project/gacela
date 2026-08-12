<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta\Fixtures;

use Gacela\Framework\Attribute\Provides;

/**
 * A return type written relative to the class declaring it.
 *
 * PHP 8.5 hands these back from `getName()` already resolved, and earlier
 * versions hand back the literal word -- so this fixture is what keeps the two
 * from producing different metadata.
 */
final class RelativeReturnTypeProvider
{
    #[Provides('ITSELF')]
    public function itself(): self
    {
        return $this;
    }

    /**
     * The one relative type PHP 8.5 leaves unresolved, so it needs resolving on
     * every supported version rather than only on the older ones.
     */
    #[Provides('LATE_BOUND')]
    public function lateBound(): static
    {
        return $this;
    }

}
