<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class CleanFactory extends AbstractFactory
{
    public function createThing(): string
    {
        return 'thing';
    }
}
