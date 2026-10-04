<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap\ResetFromGacelaFile\Library\Pay;

use Gacela\Framework\AbstractFactory;

class PayFactory extends AbstractFactory
{
    public function who(): string
    {
        return 'library';
    }
}
