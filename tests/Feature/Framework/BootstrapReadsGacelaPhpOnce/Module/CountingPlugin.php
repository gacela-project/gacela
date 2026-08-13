<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BootstrapReadsGacelaPhpOnce\Module;

/**
 * Counts its own invocations, statically: a plugin declared once has to run
 * once, and the count is the only way to see that it did not.
 */
final class CountingPlugin
{
    public static int $runs = 0;

    public function __invoke(): void
    {
        ++self::$runs;
    }

    public static function reset(): void
    {
        self::$runs = 0;
    }
}
