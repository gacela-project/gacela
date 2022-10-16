<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Profiler;

final class CustomServicesJsonProfiler extends AbstractJsonFileProfiler
{
    public const FILENAME = 'gacela-custom-services.json';

    protected function getProfilerFilename(): string
    {
        return self::FILENAME;
    }
}
