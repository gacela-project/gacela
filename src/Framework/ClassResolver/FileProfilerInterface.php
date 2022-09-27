<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

interface FileProfilerInterface
{
    public function updateFileCache(array $cache): void;
}
