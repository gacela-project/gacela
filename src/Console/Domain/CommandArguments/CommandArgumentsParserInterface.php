<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\CommandArguments;

interface CommandArgumentsParserInterface
{
    /**
     * @param string $desiredNamespace The location of the new module. For example: App/TestModule
     */
    public function parse(string $desiredNamespace): CommandArguments;
}
