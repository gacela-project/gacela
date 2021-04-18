<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\ReadModel\CommandArguments;

interface MakerInterface
{
    public function make(CommandArguments $commandArguments): void;
}
