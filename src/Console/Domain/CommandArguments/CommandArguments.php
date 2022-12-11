<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\CommandArguments;

final class CommandArguments
{
    public function __construct(
        private string $namespace,
        private string $directory,
    ) {
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function basename(): string
    {
        return basename($this->directory);
    }
}
