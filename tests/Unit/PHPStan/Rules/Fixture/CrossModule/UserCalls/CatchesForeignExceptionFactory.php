<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\UserCalls;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopException;

/**
 * A neighbour reading another module's exception type. Taken as a parameter so
 * the only crossing is the method call: `new ShopException(...)` would also be a
 * named reference, which is the sibling rule's finding rather than this one's.
 */
final class CatchesForeignExceptionFactory
{
    public function describe(ShopException $exception): string
    {
        return $exception->getMessage();
    }
}
