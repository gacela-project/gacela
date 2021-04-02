<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\Framework\AbstractFacade;
use InvalidArgumentException;

/**
 * @method CodeGeneratorFactory getFactory()
 */
final class CodeGeneratorFacade extends AbstractFacade
{
    public const HELP_TEXT = <<<HELP
Usage: gacela [command]

Commands:
    generate:facade <root-namespace> <target-directory>
        Generate a new Facade.
        
    generate:factory <root-namespace> <target-directory>
        Generate a new Factory.
    
    generate:config <root-namespace> <target-directory>
        Generate a new Config.
    
    generate:module <root-namespace> <target-directory>
        If no <root-namespace> is provided, it will use 'App' by default.
        If no <target-directory> is provided, it will use 'src/Generated' by default.

    help
        Show this help message.

HELP;

    /**
     * @throws InvalidArgumentException
     */
    public function runCommand(string $commandName, array $arguments = []): void
    {
        switch ($commandName) {
            case 'generate:facade':
                $this->executeGenerateFacade($arguments);
                break;
            case 'generate:factory':
                $this->executeGenerateFactory($arguments);
                break;
            case 'generate:config':
                $this->executeGenerateConfig($arguments);
                break;
            case 'generate:module':
                $this->executeGenerateModule($arguments);
                break;
            default:
                throw new InvalidArgumentException(self::HELP_TEXT);
        }
    }

    private function executeGenerateFacade(array $arguments): void
    {
        [$rootNamespace, $targetDirectory] = $arguments;

        $this->getFactory()
            ->createFacadeGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateFactory(array $arguments): void
    {
        [$rootNamespace, $targetDirectory] = $arguments;

        $this->getFactory()
            ->createFactoryGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateConfig(array $arguments): void
    {
        [$rootNamespace, $targetDirectory] = $arguments;

        $this->getFactory()
            ->createConfigGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateModule(array $arguments): void
    {
        [$rootNamespace, $targetDirectory] = array_pad($arguments, 2, null);

        $this->getFactory()
            ->createModuleGenerator()
            ->generate($rootNamespace ?? 'App', $targetDirectory ?? 'src/Generated');
    }
}
