<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServicesLevel\CustomModule;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\CustomServiceInterface;

final class Config extends AbstractConfig implements CustomServiceInterface
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

    public function ok(): string
    {
        return 'config-ok';
    }
}
