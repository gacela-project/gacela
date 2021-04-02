<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

interface MakerInterface
{
    public function make(string $rootNamespace, string $targetDirectory): void;
}
