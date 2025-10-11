<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Override;

final class CustomServicesPhpCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-custom-services.php';

    #[Override]
    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
