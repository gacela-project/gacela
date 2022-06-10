<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\AbstractFileCache;

final class CustomServicesCache extends AbstractFileCache
{
    public const CACHE_FILENAME = '.gacela-custom-services.cache';

    /**
     * @internal
     *
     * @return array<string,string>
     */
    public static function getAll(): array
    {
        return self::$cache;
    }

    protected function getCacheFilename(): string
    {
        return self::CACHE_FILENAME;
    }
}
