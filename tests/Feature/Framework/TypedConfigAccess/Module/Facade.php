<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TypedConfigAccess\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<Factory>
 */
final class Facade extends AbstractFacade
{
    /**
     * @return array{name: string, retries: int, ratio: float, enabled: bool, tags: array<array-key, mixed>, timeout: int}
     */
    public function readTypedValues(): array
    {
        return $this->getFactory()->readTypedValues();
    }
}
