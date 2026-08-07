<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ValidateConfig\Fixtures;

final class SideEffectingImplementation implements SomeContract
{
    public static int $constructionCount = 0;

    public function __construct()
    {
        ++self::$constructionCount;
    }
}
