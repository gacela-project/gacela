<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\DocBlockResolver\Module;

use Gacela\Framework\AbstractFactory;

/**
 * @method FakeConfig getConfig()
 */
final class FakeFactory extends AbstractFactory
{
    public function createString(): string
    {
        return $this->getConfig()->getString();
    }
}
