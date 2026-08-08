<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

use Gacela\Framework\AbstractFacade;
use Stringable;

/**
 * The facade's own interface is declared second on purpose: reading only the
 * first interface a class implements would find `Stringable`, match nothing,
 * and report no drift at all.
 */
final class SecondPositionFacade extends AbstractFacade implements Stringable, SecondPositionFacadeInterface
{
    public function __toString(): string
    {
        return 'second';
    }

    public function declared(): string
    {
        return 'declared';
    }

    public function forgotten(): string
    {
        return 'drift';
    }
}
