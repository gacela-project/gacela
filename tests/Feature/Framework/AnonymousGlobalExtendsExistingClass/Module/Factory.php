<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\AnonymousGlobalExtendsExistingClass\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValue;

/**
 * @method Config getConfig()
 */
class Factory extends AbstractFactory
{
    public function createDomainService(): StringValue
    {
        return new StringValue($this->getConfigValue());
    }

    public function getConfigValue(): string
    {
        return $this->getConfig()->getValue();
    }
}
