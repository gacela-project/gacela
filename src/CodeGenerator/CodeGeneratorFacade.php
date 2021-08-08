<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator;

use Gacela\CodeGenerator\Infrastructure\Command\MakeFileCommand;
use Gacela\CodeGenerator\Infrastructure\Command\MakeModuleCommand;
use Gacela\Framework\AbstractFacade;

/**
 * @method CodeGeneratorFactory getFactory()
 */
final class CodeGeneratorFacade extends AbstractFacade
{
    public function getMakerModuleCommand(): MakeModuleCommand
    {
        return $this->getFactory()->createMakerModuleCommand();
    }

    public function getMakerFileCommand(): MakeFileCommand
    {
        return $this->getFactory()->createMakerFileCommand();
    }
}
