<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\CommandArguments\CommandArgumentsParser;
use Gacela\Console\Domain\CommandArguments\CommandArgumentsParserInterface;
use Gacela\Console\Domain\FileContent\FileContentGenerator;
use Gacela\Console\Domain\FileContent\FileContentGeneratorInterface;
use Gacela\Console\Domain\FileContent\FileContentIoInterface;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizerInterface;
use Gacela\Console\Infrastructure\FileContentIo;
use Gacela\Framework\AbstractFactory;
use Symfony\Component\Console\Command\Command;

/**
 * @method ConsoleConfig getConfig()
 */
final class ConsoleFactory extends AbstractFactory
{
    /**
     * @return list<Command>
     *
     * @psalm-suppress MixedReturnTypeCoercion
     */
    public function getConsoleCommands(): array
    {
        return (array)$this->getProvidedDependency(ConsoleDependencyProvider::COMMANDS);
    }

    public function createCommandArgumentsParser(): CommandArgumentsParserInterface
    {
        return new CommandArgumentsParser(
            $this->getConfig()->getComposerJsonContentAsArray(),
        );
    }

    public function createFilenameSanitizer(): FilenameSanitizerInterface
    {
        return new FilenameSanitizer();
    }

    public function createFileContentGenerator(): FileContentGeneratorInterface
    {
        return new FileContentGenerator(
            $this->createFileContentIo(),
            $this->getTemplateByFilenameMap(),
        );
    }

    private function createFileContentIo(): FileContentIoInterface
    {
        return new FileContentIo();
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion
     *
     * @return array<string,string>
     */
    private function getTemplateByFilenameMap(): array
    {
        return (array)$this->getProvidedDependency(ConsoleDependencyProvider::TEMPLATE_BY_FILENAME_MAP);
    }
}
