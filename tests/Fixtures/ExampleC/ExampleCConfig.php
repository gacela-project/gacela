<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC;

use Gacela\AbstractConfig;

final class ExampleCConfig extends AbstractConfig
{
    public function getNumber(): int
    {
        return $this->get('test-number');
    }
}
