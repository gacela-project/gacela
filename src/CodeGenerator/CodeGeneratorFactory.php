<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FilenameSanitizer;
use Gacela\CodeGenerator\Infrastructure\Command\MakeFileCommand;
use Gacela\CodeGenerator\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\AbstractFactory;

/**
 * @method CodeGeneratorConfig getConfig()
 */
final class CodeGeneratorFactory extends AbstractFactory
{
    public function createMakerModuleCommand(): MakeModuleCommand
    {
        return new MakeModuleCommand(
            $this->createCommandArgumentsParser(),
            $this->createFileContentGenerator()
        );
    }

    public function createMakerFileCommand(): MakeFileCommand
    {
        return new MakeFileCommand(
            $this->createCommandArgumentsParser(),
            $this->createFilenameSanitizer(),
            $this->createFileContentGenerator()
        );
    }

    private function createCommandArgumentsParser(): CommandArgumentsParser
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray()
        );
    }

    private function createFilenameSanitizer(): FilenameSanitizer
    {
        return new FilenameSanitizer();
    }

    private function createFileContentGenerator(): FileContentGenerator
    {
        return new FileContentGenerator($this->getConfig());
    }
}
