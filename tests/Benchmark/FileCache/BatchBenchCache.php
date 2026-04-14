<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

final class BatchBenchCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-batch-bench.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
