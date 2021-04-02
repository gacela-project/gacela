<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\Command\MakerInterface;
use Gacela\Framework\AbstractFacade;
use InvalidArgumentException;

/**
 * @method CodeGeneratorFactory getFactory()
 */
final class CodeGeneratorFacade extends AbstractFacade
{
    public const HELP_TEXT = <<<'HELP'
Usage: gacela [command]

Commands:
    make:facade <root-namespace> <target-directory>
        Create a new Facade.

    make:factory <root-namespace> <target-directory>
        Create a new Factory.

    make:config <root-namespace> <target-directory>
        Create a new Config.

    make:dependency-provider <root-namespace> <target-directory>
        Create a new Config.

    make:module <root-namespace> <target-directory>
        Create a Facade, Factory and Config inside

    help
        Show this help message.

HELP;

    private const MAKE_FACADE_COMMAND = 'make:facade';
    private const MAKE_FACTORY_COMMAND = 'make:factory';
    private const MAKE_CONFIG_COMMAND = 'make:config';
    private const MAKE_DEPENDENCY_PROVIDER_COMMAND = 'make:dependency-provider';
    private const MAKE_MODULE_COMMAND = 'make:module';

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

        $this->createMaker($commandName)->generate($rootNamespace, $targetDirectory);
    }

    private function createMaker(string $commandName): MakerInterface
    {
        switch ($commandName) {
            case self::MAKE_FACADE_COMMAND:
                return $this->getFactory()->createFacadeMaker();
            case self::MAKE_FACTORY_COMMAND:
                return $this->getFactory()->createFactoryMaker();
            case self::MAKE_CONFIG_COMMAND:
                return $this->getFactory()->createConfigMaker();
            case self::MAKE_DEPENDENCY_PROVIDER_COMMAND:
                return $this->getFactory()->createDependencyProviderMaker();
            case self::MAKE_MODULE_COMMAND:
                return $this->getFactory()->createModuleMaker();
            default:
                throw new InvalidArgumentException(self::HELP_TEXT);
        }
    }
}
