<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\AbstractFileProfiler;

final class CustomServicesProfiler extends AbstractFileProfiler
{
    public const FILENAME = 'gacela-custom-services.php';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
