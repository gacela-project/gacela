<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\DocBlockService;

use Gacela\Framework\ClassResolver\AbstractJsonFileProfiler;

final class CustomServicesJsonProfiler extends AbstractJsonFileProfiler
{
    public const FILENAME = 'gacela-custom-services.json';

    protected function getCacheFilename(): string
    {
        return self::FILENAME;
    }
}
