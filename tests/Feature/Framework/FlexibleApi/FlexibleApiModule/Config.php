<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FlexibleApi\FlexibleApiModule;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    /**
     * @return array<string,int>
     */
    public function getAllKeyValues(): array
    {
        return [
            'from-config' => (int)$this->get('from-config'),
        ];
    }
}
