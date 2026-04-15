<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Plugins\Handler;

final class CountingHandler
{
    public static int $instantiations = 0;

    public function __construct()
    {
        ++self::$instantiations;
    }
}
