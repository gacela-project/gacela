<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolver\ServiceMap;

/**
 * Declares a custom service by attribute -- the supported way, and the one 3.0
 * keeps -- naming a class that is not a pillar but whose name contains one of
 * their words.
 */
#[ServiceMap(method: 'getLoader', className: FakeConfigurationLoader::class)]
final class FakeLoaderCommand
{
    use \Gacela\Framework\ServiceResolverAwareTrait;
}
