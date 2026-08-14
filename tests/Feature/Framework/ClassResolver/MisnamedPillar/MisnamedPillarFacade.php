<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\MisnamedPillar;

use Gacela\Framework\AbstractFacade;

/**
 * Beside it sits a Factory under a misspelled name, so this module resolves the
 * stand-in exactly as one with no Factory at all does -- and the message that
 * follows tells the reader to write a file they already wrote.
 */
final class MisnamedPillarFacade extends AbstractFacade
{
}
