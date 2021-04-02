<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

interface MakerInterface
{
    public function generate(string $rootNamespace, string $targetDirectory): void;
}
