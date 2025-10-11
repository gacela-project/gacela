<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\OverrideExistingResolvedClass\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    public function getSomething(): string
    {
        return $this->getFactory()
            ->createDomainService()
            ->value();
    }
}
