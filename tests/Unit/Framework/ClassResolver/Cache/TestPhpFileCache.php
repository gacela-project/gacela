<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver\Cache;

use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;

final class TestPhpFileCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-batch-test.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
