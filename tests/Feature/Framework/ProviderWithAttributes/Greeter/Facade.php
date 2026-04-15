<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ProviderWithAttributes\Greeter;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public function greet(string $name): string
    {
        return $this->getFactory()->createGreetingService()->greet($name);
    }

    /**
     * @return list<string>
     */
    public function prefixes(): array
    {
        return $this->getFactory()->createPrefixes();
    }
}
