<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class FacadeMaker extends AbstractMaker
{
    protected function className(): string
    {
        return 'Facade';
    }
}
