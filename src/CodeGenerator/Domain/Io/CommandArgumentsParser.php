<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Io;

use Gacela\CodeGenerator\Domain\ReadModel\CommandArguments;
use InvalidArgumentException;

final class CommandArgumentsParser
{
    /**
     * @throws InvalidArgumentException
     */
    public function parse(array $arguments): CommandArguments
    {
        [$rootNamespace, $targetDirectory] = array_pad($arguments, 2, null);

        if ($rootNamespace === null) {
            throw new InvalidArgumentException('Expected 1st argument to be root-namespace of the project');
        }

        if ($targetDirectory === null) {
            throw new InvalidArgumentException('Expected 2nd argument to be target-directory inside the project');
        }

        return new CommandArguments($rootNamespace, $targetDirectory);
    }
}
