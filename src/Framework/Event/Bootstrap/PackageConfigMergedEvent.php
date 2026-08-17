<?php

declare(strict_types=1);

namespace Gacela\Framework\Event\Bootstrap;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * An installed package's declared Gacela config was read and merged.
 *
 * One per discovered package, dispatched in merge order, which is the order
 * `position()` counts. Dispatched *after* the whole configuration is assembled
 * rather than as each package is merged: the dispatcher is built from the merged
 * listeners, so firing during the merge would fire before the project's own
 * listeners exist, and a listener in `gacela.php` -- the natural place to log
 * what a boot picked up -- would never see it.
 *
 * `configFile()` is the absolute path of the file that ran, so a boot that picked
 * up something unexpected names it rather than describing it.
 */
final class PackageConfigMergedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $packageName,
        private readonly string $configFile,
        private readonly int $position,
    ) {
    }

    public function packageName(): string
    {
        return $this->packageName;
    }

    public function configFile(): string
    {
        return $this->configFile;
    }

    /**
     * 1-based place in the merge order.
     */
    public function position(): int
    {
        return $this->position;
    }

    public function toString(): string
    {
        return sprintf(
            '%s {packageName:"%s", configFile:"%s", position:%d}',
            self::class,
            $this->packageName,
            $this->configFile,
            $this->position,
        );
    }
}
