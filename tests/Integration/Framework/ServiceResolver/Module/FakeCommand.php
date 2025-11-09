<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * @method FakeFacade getFacade()
 * @method FakeRandomService getRandom()
 * @method FakeUnknown getUnknown() This does not exist and will ended up in an Error
 */
final class FakeCommand
{
    use ServiceResolverAwareTrait;
}
