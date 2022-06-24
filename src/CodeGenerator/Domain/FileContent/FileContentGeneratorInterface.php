<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\FileContent;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArguments;

interface FileContentGeneratorInterface
{
    /**
     * @return string path result where the file was generated
     */
    public function generate(CommandArguments $commandArguments, string $filename, bool $withShortName = false): string;
}
