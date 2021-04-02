<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\Command\ConfigMaker;
use Gacela\CodeGenerator\Domain\Command\DependencyProviderMaker;
use Gacela\CodeGenerator\Domain\Command\FacadeMaker;
use Gacela\CodeGenerator\Domain\Command\FactoryMaker;
use Gacela\CodeGenerator\Domain\Command\ModuleMaker;
use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;
use Gacela\CodeGenerator\Infrastructure\Io\SystemGeneratorIo;
use Gacela\Framework\AbstractFactory;

final class CodeGeneratorFactory extends AbstractFactory
{
    public function createFacadeMaker(): FacadeMaker
    {
        return new FacadeMaker(
            $this->createGeneratorIo()
        );
    }

    public function createFactoryMaker(): FactoryMaker
    {
        return new FactoryMaker(
            $this->createGeneratorIo()
        );
    }

    public function createConfigMaker(): ConfigMaker
    {
        return new ConfigMaker(
            $this->createGeneratorIo()
        );
    }

    public function createDependencyProviderMaker(): DependencyProviderMaker
    {
        return new DependencyProviderMaker(
            $this->createGeneratorIo()
        );
    }

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

    private function createGeneratorIo(): GeneratorIoInterface
    {
        return new SystemGeneratorIo();
    }
}
