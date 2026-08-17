<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail;

/**
 * Where this package's channel and its listener write, so a test can see that
 * both of them ran.
 */
final class AuditRecorder
{
    /** @var list<string> */
    private static array $records = [];

    public static function record(string $message): void
    {
        self::$records[] = $message;
    }

    public static function reset(): void
    {
        self::$records = [];
    }

    /**
     * @return list<string>
     */
    public static function records(): array
    {
        return self::$records;
    }
}
