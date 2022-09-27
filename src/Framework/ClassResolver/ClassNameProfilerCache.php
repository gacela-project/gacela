<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

final class ClassNameProfilerCache extends AbstractFileCache
{
    public const CACHE_FILENAME = '.gacela-class-names.cache';

    protected function getCacheFilename(): string
    {
        return self::CACHE_FILENAME;
    }
}
