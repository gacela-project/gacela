<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\TypedConfigAccess\Module;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    /**
     * @return array{name: string, retries: int, ratio: float, enabled: bool, tags: array<array-key, mixed>, timeout: int}
     */
    public function readTypedValues(): array
    {
        return [
            'name' => $this->getString('name'),
            'retries' => $this->getInt('retries'),
            'ratio' => $this->getFloat('ratio'),
            'enabled' => $this->getBool('enabled'),
            'tags' => $this->getArray('tags'),
            'timeout' => $this->getInt('missing-timeout', 30),
        ];
    }
}
