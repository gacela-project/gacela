<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

final class CustomServicesPhpCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-custom-services.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
