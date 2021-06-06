<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\ValueObject;

final class CommandArguments
{
    private string $namespace;
    private string $targetDirectory;

    public function __construct(string $namespace, string $targetDirectory)
    {
        $this->namespace = $namespace;
        $this->targetDirectory = $targetDirectory;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function dirname(): string
    {
        return basename($this->targetDirectory);
    }
}
