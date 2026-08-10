<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

/**
 * Counts its constructions, so a test can tell "configured" apart from
 * "built" -- and carries a name only Laravel supplies, so a test can tell
 * Laravel's instance apart from an autowired one.
 */
final class CountingService implements ServiceContract
{
    public const FROM_LARAVEL = 'from-laravel';

    public static int $constructed = 0;

    public function __construct(
        private readonly string $name = 'autowired',
    ) {
        ++self::$constructed;
    }

    public function name(): string
    {
        return $this->name;
    }
}
