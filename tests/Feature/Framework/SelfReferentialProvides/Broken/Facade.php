<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\SelfReferentialProvides\Broken;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function sound(): string
    {
        return $this->getFactory()->createSound();
    }

    public function selfReferential(): mixed
    {
        return $this->getFactory()->createSelfReferential();
    }
}
