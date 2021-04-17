<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class ConfigMaker extends AbstractMaker
{
    protected function className(): string
    {
        return 'Config';
    }
}
