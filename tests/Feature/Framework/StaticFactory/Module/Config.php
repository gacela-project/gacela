<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFactory\Module;

use Gacela\Framework\AbstractConfig;

final class Config extends AbstractConfig
{
    public const STR = 'from config';

    public function getString(): string
    {
        return self::STR;
    }
}
