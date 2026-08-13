<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver\Module;

use Gacela\Framework\ServiceResolverAwareTrait;

/**
 * Convenience wrapper. Use getFacade() to reach the module.
 *
 * The sentence above is the fixture. The resolver used to take the first line
 * *containing* the accessor's name and read its fourth space-separated token,
 * so this one answered for the tag below with a class called `wrapper.` -- and
 * the class failed to resolve with "Missing the concrete return type", on a
 * docblock that states it.
 *
 * @method FakeFacade getFacade()
 */
final class FakeProseCommand
{
    use ServiceResolverAwareTrait;
}
