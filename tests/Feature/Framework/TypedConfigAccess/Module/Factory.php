<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TypedConfigAccess\Module;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<Config>
 */
final class Factory extends AbstractFactory
{
    /**
     * @return array{name: string, retries: int, ratio: float, enabled: bool, tags: array<array-key, mixed>, timeout: int}
     */
    public function readTypedValues(): array
    {
        return $this->getConfig()->readTypedValues();
    }
}
