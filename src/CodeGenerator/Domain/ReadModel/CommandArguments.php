<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\ReadModel;

final class CommandArguments
{
    private string $namespace;
    private string $directory;

    public function __construct(string $namespace, string $directory)
    {
        $this->namespace = $namespace;
        $this->directory = $directory;
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    public function directory(): string
    {
        return $this->directory;
    }
}
