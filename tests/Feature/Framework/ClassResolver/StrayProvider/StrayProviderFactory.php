<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\StrayProvider;

use Gacela\Framework\AbstractFactory;

/**
 * The caller. Its directory is what the hint reads, so it only has to be a
 * class that lives beside the misnamed Provider.
 *
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class StrayProviderFactory extends AbstractFactory
{
}
