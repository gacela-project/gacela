<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

/**
 * Counts its own construction, which is how a test can tell "reachable from
 * Gacela" from "built because Gacela was configured".
 */
final class CountingService
{
    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public function name(): string
    {
        return 'counting';
    }
}
