<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ClassNameProfiler extends AbstractFileProfiler
{
    public const CACHE_FILENAME = '.gacela-class-names.php';

    protected function getCacheFilename(): string
    {
        return self::CACHE_FILENAME;
    }
}
