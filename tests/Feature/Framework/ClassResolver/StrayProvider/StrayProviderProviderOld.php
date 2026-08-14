<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ClassResolver\StrayProvider;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * A second stray beside the misspelled one: the copy left behind by a rename.
 *
 * Two of them rather than one on purpose. With a single hint the block is one
 * line, and the tips below it start with a blank line -- so dropping the
 * newline that ends a hint line changes nothing anyone can see. It takes two
 * for them to run together.
 */
final class StrayProviderProviderOld extends AbstractProvider
{
    public function provideModuleDependencies(Container $container): void
    {
    }
}
