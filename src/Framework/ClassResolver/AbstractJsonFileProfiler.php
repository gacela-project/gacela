<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver;

use RuntimeException;

use function json_encode;

abstract class AbstractJsonFileProfiler implements FileProfilerInterface
{
    private string $cacheDir;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = $cacheDir;
    }

    public function updateProfiler(array $cache): void
    {
        $fileContent = json_encode($cache, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        file_put_contents($this->getAbsoluteCacheFilename(), $fileContent);
    }

    abstract protected function getCacheFilename(): string;

    private function getAbsoluteCacheFilename(): string
    {
        if (!is_dir($this->cacheDir)
            && !mkdir($concurrentDirectory = $this->cacheDir, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this->cacheDir . '/' . $this->getCacheFilename();
    }
}
