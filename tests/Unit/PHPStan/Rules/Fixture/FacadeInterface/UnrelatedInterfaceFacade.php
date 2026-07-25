<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

use Gacela\Framework\AbstractFacade;
use Stringable;

/**
 * A facade implementing something that is not its own `*FacadeInterface` has
 * opted into nothing, so it is not drifting.
 */
final class UnrelatedInterfaceFacade extends AbstractFacade implements Stringable
{
    public function __toString(): string
    {
        return 'unrelated';
    }

    public function notInAnyInterface(): string
    {
        return 'fine';
    }
}
