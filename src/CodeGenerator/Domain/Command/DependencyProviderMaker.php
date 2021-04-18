<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class DependencyProviderMaker extends AbstractMaker
{
    protected function className(): string
    {
        return 'DependencyProvider';
    }
}
