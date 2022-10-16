<?php

declare(strict_types=1);

namespace Gacela\Framework\ClassResolver\Profiler;

use RuntimeException;

use function json_encode;

abstract class AbstractJsonFileProfiler implements FileProfilerInterface
{
    private string $profilerDir;

    public function __construct(string $profilerDir)
    {
        $this->profilerDir = $profilerDir;
    }

    public function updateProfiler(array $data): void
    {
        $fileContent = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

        file_put_contents($this->getAbsoluteProfilerFilename(), $fileContent);
    }

    abstract protected function getProfilerFilename(): string;

    private function getAbsoluteProfilerFilename(): string
    {
        if (!is_dir($this->profilerDir)
            && !mkdir($concurrentDirectory = $this->profilerDir, 0777, true)
            && !is_dir($concurrentDirectory)
        ) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }

        return $this->profilerDir . DIRECTORY_SEPARATOR . $this->getProfilerFilename();
    }
}
