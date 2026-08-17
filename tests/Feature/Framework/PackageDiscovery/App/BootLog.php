<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery\App;

use Gacela\Framework\Event\Bootstrap\PackageConfigMergedEvent;

use function sprintf;

/**
 * What a listener in the application's `gacela.php` writes down about the boot.
 *
 * This is the reason `PackageConfigMergedEvent` is dispatched after the merge
 * rather than during it: a listener registered here does not exist yet while the
 * packages are being merged.
 */
final class BootLog
{
    /** @var list<string> */
    private static array $lines = [];

    public static function recordMerge(PackageConfigMergedEvent $event): void
    {
        self::$lines[] = sprintf('%d %s', $event->position(), $event->packageName());
    }

    public static function reset(): void
    {
        self::$lines = [];
    }

    /**
     * @return list<string>
     */
    public static function lines(): array
    {
        return self::$lines;
    }
}
