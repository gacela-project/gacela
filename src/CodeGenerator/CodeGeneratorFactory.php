<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\Command\ConfigMaker;
use Gacela\CodeGenerator\Domain\Command\DependencyProviderMaker;
use Gacela\CodeGenerator\Domain\Command\FacadeMaker;
use Gacela\CodeGenerator\Domain\Command\FactoryMaker;
use Gacela\CodeGenerator\Domain\Command\ModuleMaker;
use Gacela\CodeGenerator\Domain\Io\CommandArgumentsParser;
use Gacela\CodeGenerator\Domain\Io\MakerIoInterface;
use Gacela\CodeGenerator\Infrastructure\Io\SystemMakerIo;
use Gacela\Framework\AbstractFactory;

/**
 * @method CodeGeneratorConfig getConfig()
 */
final class CodeGeneratorFactory extends AbstractFactory
{
    public function createModuleMaker(): ModuleMaker
    {
        return new ModuleMaker(
            $this->createGeneratorIo(),
            [
                $this->createFacadeMaker(),
                $this->createFactoryMaker(),
                $this->createConfigMaker(),
                $this->createDependencyProviderMaker(),
            ]
        );
    }

    public function createFacadeMaker(): FacadeMaker
    {
        return new FacadeMaker(
            $this->createGeneratorIo(),
            $this->getConfig()->getFacadeMakerTemplate()
        );
    }

    public function createFactoryMaker(): FactoryMaker
    {
        return new FactoryMaker(
            $this->createGeneratorIo(),
            $this->getConfig()->getFactoryMakerTemplate()
        );
    }

    public function createConfigMaker(): ConfigMaker
    {
        return new ConfigMaker(
            $this->createGeneratorIo(),
            $this->getConfig()->getConfigMakerTemplate()
        );
    }

    public function createDependencyProviderMaker(): DependencyProviderMaker
    {
        return new DependencyProviderMaker(
            $this->createGeneratorIo(),
            $this->getConfig()->getDependencyProviderMakerTemplate()
        );
    }

    private function createGeneratorIo(): MakerIoInterface
    {
        return new SystemMakerIo();
    }

    public function createCommandArgumentsParser(): CommandArgumentsParser
    {
        return new CommandArgumentsParser();
    }
}
