<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Cache;

use Override;

final class ClassNamePhpCache extends AbstractPhpFileCache
{
    public const FILENAME = 'gacela-class-names.php';

    #[Override]
    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
