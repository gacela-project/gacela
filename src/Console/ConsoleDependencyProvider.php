<?php

declare(strict_types=1);

namespace Gacela\Console;

use Gacela\Console\Domain\FilenameSanitizer\FilenameSanitizer;
use Gacela\Console\Infrastructure\Command\MakeFileCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

/**
 * @method ConsoleConfig getConfig()
 */
final class ConsoleDependencyProvider extends AbstractDependencyProvider
{
    public const COMMANDS = 'COMMANDS';

    public const TEMPLATE_BY_FILENAME_MAP = 'TEMPLATE_FILENAME_MAP';

    public function provideModuleDependencies(Container $container): void
    {
        $this->addCommands($container);
        $this->addTemplateByFilenameMap($container);
    }

    private function addCommands(Container $container): void
    {
        $container->set(self::COMMANDS, static fn () => [
            new MakeFileCommand(),
            new MakeModuleCommand(),
        ]);
    }

    private function addTemplateByFilenameMap(Container $container): void
    {
        $codeTemplate = $this->getConfig();

        $container->set(self::TEMPLATE_BY_FILENAME_MAP, static fn () => [
            FilenameSanitizer::FACADE => $codeTemplate->getFacadeMakerTemplate(),
            FilenameSanitizer::FACTORY => $codeTemplate->getFactoryMakerTemplate(),
            FilenameSanitizer::CONFIG => $codeTemplate->getConfigMakerTemplate(),
            FilenameSanitizer::DEPENDENCY_PROVIDER => $codeTemplate->getDependencyProviderMakerTemplate(),
        ]);
    }
}
