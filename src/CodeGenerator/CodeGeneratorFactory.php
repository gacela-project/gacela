<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Infrastructure\Command\MakeFileCommand;
use Gacela\Framework\AbstractFactory;

/**
 * @method CodeGeneratorConfig getConfig()
 */
final class CodeGeneratorFactory extends AbstractFactory
{
    public function createMakerCommand(): MakeFileCommand
    {
        return new MakeFileCommand(
            $this->createCommandArgumentsParser(),
            $this->createFileContentGenerator()
        );
    }

    private function createCommandArgumentsParser(): CommandArgumentsParser
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray()
        );
    }

    private function createFileContentGenerator(): FileContentGenerator
    {
        return new FileContentGenerator($this->getConfig());
    }
}
