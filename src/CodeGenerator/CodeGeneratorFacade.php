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
    make:module <root-namespace> <target-directory>
        Create a new Facade, Factory, Config, and DependencyProvider.

    make:facade <root-namespace> <target-directory>
        Create a new Facade.

    make:factory <root-namespace> <target-directory>
        Create a new Factory.

    make:config <root-namespace> <target-directory>
        Create a new Config.

    make:dependency-provider <root-namespace> <target-directory>
        Create a new DependencyProvider.

    help
        Show this help message.

HELP;

    private const MAKE_MODULE_COMMAND = 'make:module';
    private const MAKE_FACADE_COMMAND = 'make:facade';
    private const MAKE_FACTORY_COMMAND = 'make:factory';
    private const MAKE_CONFIG_COMMAND = 'make:config';
    private const MAKE_DEPENDENCY_PROVIDER_COMMAND = 'make:dependency-provider';

    /**
     * @throws InvalidArgumentException
     */
    public function runCommand(string $commandName, array $arguments = []): void
    {
        $commandArguments = $this->getFactory()
            ->createCommandArgumentsParser()
            ->parse($arguments);

        $this->createMaker($commandName)->make($commandArguments);
    }

    private function createMaker(string $commandName): MakerInterface
    {
        switch ($commandName) {
            case self::MAKE_MODULE_COMMAND:
                return $this->getFactory()->createModuleMaker();
            case self::MAKE_FACADE_COMMAND:
                return $this->getFactory()->createFacadeMaker();
            case self::MAKE_FACTORY_COMMAND:
                return $this->getFactory()->createFactoryMaker();
            case self::MAKE_CONFIG_COMMAND:
                return $this->getFactory()->createConfigMaker();
            case self::MAKE_DEPENDENCY_PROVIDER_COMMAND:
                return $this->getFactory()->createDependencyProviderMaker();
            default:
                throw new InvalidArgumentException(self::HELP_TEXT);
        }
    }
}
