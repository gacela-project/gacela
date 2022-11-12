<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerFactory\Module;

use Gacela\Framework\AbstractFacade;
use GacelaTest\Fixtures\StringValue;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function getCachedSeed(): string
    {
        return $this->getFactory()->getCachedSeed();
    }

    public function getRandomSeed(): string
    {
        return $this->getFactory()->getRandomSeed();
    }

    public function getRandomStringValue(): StringValue
    {
        return $this->getFactory()->getRandomStringValue();
    }
}
