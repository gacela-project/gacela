<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class ClassNamePhpCache extends AbstractFileCache
{
    public const FILENAME = 'gacela-class-names.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
