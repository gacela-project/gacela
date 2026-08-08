<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

use Gacela\Framework\AbstractFactory;

/**
 * @extends AbstractFactory<\Gacela\Framework\AbstractConfig>
 */
final class ConsumerFactory extends AbstractFactory
{
    public function createKnown(): string
    {
        return 'known';
    }
}
