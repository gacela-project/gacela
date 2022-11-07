<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver\Module;

use Gacela\Framework\DocBlockResolverAwareTrait;

/**
 * @method FakeFacade getFacade()
 * @method FakeRandomService getRandom()
 */
final class FakeCommand
{
    use DocBlockResolverAwareTrait;
}
