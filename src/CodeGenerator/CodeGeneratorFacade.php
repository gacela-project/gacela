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
        Generate a Facade, Factory and Config inside

    help
        Show this help message.

HELP;

    /**
     * @throws InvalidArgumentException
     */
    public function runCommand(string $commandName, array $arguments = []): void
    {
        [$rootNamespace, $targetDirectory] = array_pad($arguments, 2, null);

        if ($rootNamespace === null) {
            throw new InvalidArgumentException('Expected 1st argument to be root-namespace of the project');
        }

        if ($targetDirectory === null) {
            throw new InvalidArgumentException('Expected 2nd argument to be target-directory inside the project');
        }

        switch ($commandName) {
            case 'generate:facade':
                $this->executeGenerateFacade($rootNamespace, $targetDirectory);
                break;
            case 'generate:factory':
                $this->executeGenerateFactory($rootNamespace, $targetDirectory);
                break;
            case 'generate:config':
                $this->executeGenerateConfig($rootNamespace, $targetDirectory);
                break;
            case 'generate:module':
                $this->executeGenerateModule($rootNamespace, $targetDirectory);
                break;
            default:
                throw new InvalidArgumentException(self::HELP_TEXT);
        }
    }

    private function executeGenerateFacade(string $rootNamespace, string $targetDirectory): void
    {
        $this->getFactory()
            ->createFacadeGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateFactory(string $rootNamespace, string $targetDirectory): void
    {
        $this->getFactory()
            ->createFactoryGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateConfig(string $rootNamespace, string $targetDirectory): void
    {
        $this->getFactory()
            ->createConfigGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }

    private function executeGenerateModule(string $rootNamespace, string $targetDirectory): void
    {
        $this->getFactory()
            ->createModuleGenerator()
            ->generate($rootNamespace, $targetDirectory);
    }
}
