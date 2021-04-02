<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Domain\Generator\ConfigGenerator;
use Gacela\CodeGenerator\Domain\Generator\DependencyProviderGenerator;
use Gacela\CodeGenerator\Domain\Generator\FacadeGenerator;
use Gacela\CodeGenerator\Domain\Generator\FactoryGenerator;
use Gacela\CodeGenerator\Domain\Generator\ModuleGenerator;
use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;
use Gacela\CodeGenerator\Infrastructure\Io\SystemGeneratorIo;
use Gacela\Framework\AbstractFactory;

final class CodeGeneratorFactory extends AbstractFactory
{
    public function createFacadeGenerator(): FacadeGenerator
    {
        return new FacadeGenerator(
            $this->createGeneratorIo()
        );
    }

    public function createFactoryGenerator(): FactoryGenerator
    {
        return new FactoryGenerator(
            $this->createGeneratorIo()
        );
    }

    public function createConfigGenerator(): ConfigGenerator
    {
        return new ConfigGenerator(
            $this->createGeneratorIo()
        );
    }

    public function createDependencyProviderGenerator(): DependencyProviderGenerator
    {
        return new DependencyProviderGenerator(
            $this->createGeneratorIo()
        );
    }

    public function createModuleGenerator(): ModuleGenerator
    {
        return new ModuleGenerator(
            $this->createGeneratorIo(),
            [
                $this->createFacadeGenerator(),
                $this->createFactoryGenerator(),
                $this->createConfigGenerator(),
                $this->createDependencyProviderGenerator(),
            ]
        );
    }

    private function createGeneratorIo(): GeneratorIoInterface
    {
        return new SystemGeneratorIo();
    }
}
