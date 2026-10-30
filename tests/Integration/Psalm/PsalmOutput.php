<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use Symfony\Component\Process\Process;

final readonly class PsalmOutput
{
    private function __construct(public string $stdout, public string $stderr, public int $exitCode)
    {
    }

    /**
     * @param list<string> $command
     */
    public static function fromCommand(array $command): self
    {
        $process = new Process($command, timeout: null);
        $exitCode = $process->run();

        return new self($process->getOutput(), $process->getErrorOutput(), $exitCode);
    }

    public function combined(): string
    {
        return $this->stdout . $this->stderr;
    }

    public function isCompleteAnalysis(): bool
    {
        return $this->exitCode === 0 || $this->exitCode === 2;
    }
}
