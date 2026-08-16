<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain;

use function sprintf;

/**
 * One capture, one id. Registered with `addFactory()` rather than as an
 * ordinary binding, so every resolution is a new attempt instead of the same
 * shared object handed back twice.
 */
final class AttemptId
{
    private static int $counter = 0;

    private readonly string $value;

    public function __construct()
    {
        ++self::$counter;
        $this->value = sprintf('attempt-%d', self::$counter);
    }

    public static function reset(): void
    {
        self::$counter = 0;
    }

    public function value(): string
    {
        return $this->value;
    }
}
