<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ShopException;

/**
 * A neighbour reading another module's exception type.
 *
 * Taken as a parameter rather than caught around a `throw new`, so the only
 * crossing here is the method call. Writing `new ShopException(...)` would also
 * be a *named* reference, which the other half of the check reports -- a real
 * finding, and not the one under test.
 */
final class CatchesTheOtherModulesException
{
    public function describe(ShopException $exception): string
    {
        return $exception->getMessage();
    }
}
