<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\FakeModule;

/**
 * @method FakeModuleFactory getFactory()
 */
final class FakeModuleFacade extends FakeParentFacade
{
    public function overrideByChildMethod(): string
    {
        return $this->getFactory()->getConfig()->getKey();
    }
}
