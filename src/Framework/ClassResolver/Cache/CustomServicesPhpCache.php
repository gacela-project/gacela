<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class CustomServicesPhpCache extends AbstractFileCache
{
    public const FILENAME = 'gacela-custom-services.php';

    /**
     * @internal
     *
     * @return array<string,string>
     */
    public static function all(): array
    {
        return self::$cache;
    }

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
