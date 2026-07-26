<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

use Gacela\Framework\AbstractFactory;

final class ConsumerFactory extends AbstractFactory
{
    public function createName(): string
    {
        return 'known';
    }
}
