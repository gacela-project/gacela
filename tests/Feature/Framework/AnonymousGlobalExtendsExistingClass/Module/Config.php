<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AnonymousGlobalExtendsExistingClass\Module;

use Gacela\Framework\AbstractConfig;

class Config extends AbstractConfig
{
    public function getValue(): string
    {
        return $this->get('key');
    }
}
