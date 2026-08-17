<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Packages\InvoiceAudit;

/**
 * Where this package's listener writes, so the application can be asked whether
 * a package's listener ran without the application registering it.
 */
final class AuditTrail
{
    /** @var list<string> */
    private static array $entries = [];

    public static function record(string $entry): void
    {
        self::$entries[] = $entry;
    }

    public static function reset(): void
    {
        self::$entries = [];
    }

    /**
     * @return list<string>
     */
    public static function entries(): array
    {
        return self::$entries;
    }
}
