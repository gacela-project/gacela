<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\ReadModel;

final class CommandArguments
{
    private string $rootNamespace;
    private string $targetDirectory;

    public function __construct(string $rootNamespace, string $targetDirectory)
    {
        $this->rootNamespace = $rootNamespace;
        $this->targetDirectory = $targetDirectory;
    }

    public function rootNamespace(): string
    {
        return $this->rootNamespace;
    }

    public function targetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
