<?php

declare(strict_types=1);

namespace Gacela\Framework\Cache;

/**
 * Immutable snapshot of a {@see FileCache} directory: aggregate entry count,
 * total on-disk size in bytes and the mtime bounds across entries.
 *
 * Hit/miss ratios are intentionally NOT tracked to keep the primitive cheap;
 * higher layers that need them can wrap the cache.
 */
final class FileCacheStats
{
    public function __construct(
        public readonly int $entries,
        public readonly int $bytes,
        public readonly ?int $oldestAt,
        public readonly ?int $newestAt,
    ) {
    }
}
