<?php

declare(strict_types=1);

namespace GacelaTest\Integration\UsingConfig\SimpleModule;

use Gacela\AbstractFactory;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    public function getNumber(): int
    {
        return $this->getConfig()->getNumber();
    }
}
