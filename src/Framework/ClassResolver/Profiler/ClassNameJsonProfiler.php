<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Profiler;

final class ClassNameJsonProfiler extends AbstractJsonFileProfiler
{
    public const FILENAME = 'gacela-class-names.json';

    protected function getProfilerFilename(): string
    {
        return self::FILENAME;
    }
}
