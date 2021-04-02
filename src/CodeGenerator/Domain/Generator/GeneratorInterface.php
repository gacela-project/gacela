<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

interface GeneratorInterface
{
    public function generate(string $rootNamespace, string $targetDirectory): void;
}
