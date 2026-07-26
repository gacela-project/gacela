<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

use Gacela\Framework\AbstractFacade;

final class DriftedFacade extends AbstractFacade implements DriftedFacadeInterface
{
    /**
     * Ordered ahead of the drifted methods by cs-fixer, which is the point:
     * skipping a magic method must continue the scan, not end it.
     */
    public function __toString(): string
    {
        return 'magic';
    }

    public function declared(): string
    {
        return 'declared';
    }

    public function addedLaterAndForgotten(): string
    {
        return 'drift';
    }

    public function alsoForgotten(): int
    {
        return 2;
    }

    /**
     * Sits before the drifted methods on purpose: skipping a non-public method
     * must continue the scan, not end it.
     */
    protected function notPublic(): string
    {
        return 'ignored';
    }
}
