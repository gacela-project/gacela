<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\MissingPillar;

use Gacela\Framework\AbstractFacade;

/**
 * Deliberately alone: no Factory and no Config beside it, which is what makes
 * the stand-in resolve.
 */
final class NoFactoryFacade extends AbstractFacade
{
}
