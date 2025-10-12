<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Dependent;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade implements FacadeInterface
{
    public function greet(string $name): array
    {
        return $this->getFactory()
            ->createServiceA()
            ->greet($name);
    }
}
