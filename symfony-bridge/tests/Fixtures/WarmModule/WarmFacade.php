<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures\WarmModule;

use Gacela\Framework\AbstractFacade;

/**
 * A module for the warmer to find: with nothing to resolve there is nothing to
 * cache, and a warmer that wrote no files would look identical to one that did
 * not run.
 *
 * @extends AbstractFacade<WarmFactory>
 */
final class WarmFacade extends AbstractFacade
{
    public function name(): string
    {
        return $this->getFactory()->createName();
    }
}
