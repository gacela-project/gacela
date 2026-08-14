<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\MisnamedPillar;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFactory;

/**
 * Misspelled on purpose. It has to stay misspelled: the whole point is that the
 * Factory is written and the resolver cannot see it under this name.
 *
 * @extends AbstractFactory<AbstractConfig>
 */
final class MisnamedPillarFactroy extends AbstractFactory
{
}
