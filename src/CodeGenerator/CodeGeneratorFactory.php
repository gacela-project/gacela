<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\CommandArguments\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\CommandArguments\CommandArgumentsParserInterface;
use Gacela\CodeGenerator\Domain\FileContent\FileContentGenerator;
use Gacela\CodeGenerator\Domain\FileContent\FileContentGeneratorInterface;
use Gacela\CodeGenerator\Domain\FileContent\FileContentIoInterface;
use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\CodeGenerator\Domain\FilenameSanitizer\FilenameSanitizerInterface;
use Gacela\CodeGenerator\Infrastructure\Command\MakeFileCommand;
use Gacela\CodeGenerator\Infrastructure\Command\MakeModuleCommand;
use Gacela\CodeGenerator\Infrastructure\FileContentIo;
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
            $this->createFileContentGenerator(),
            $this->createFilenameSanitizer()
        );
    }

    public function createMakerFileCommand(): MakeFileCommand
    {
        return new MakeFileCommand(
            $this->createCommandArgumentsParser(),
            $this->createFileContentGenerator(),
            $this->createFilenameSanitizer()
        );
    }

    private function createCommandArgumentsParser(): CommandArgumentsParserInterface
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray()
        );
    }

    private function createFilenameSanitizer(): FilenameSanitizerInterface
    {
        return new FilenameSanitizer();
    }

    private function createFileContentGenerator(): FileContentGeneratorInterface
    {
        return new FileContentGenerator(
            $this->createFileContentIo(),
            $this->getTemplateByFilenameMap()
        );
    }

    private function createFileContentIo(): FileContentIoInterface
    {
        return new FileContentIo();
    }

    /**
     * @return array<string,string>
     */
    private function getTemplateByFilenameMap(): array
    {
        /** @var array<string,string> $map */
        $map = $this->getProvidedDependency(CodeGeneratorDependencyProvider::TEMPLATE_BY_FILENAME_MAP);
        return $map;
    }
}
