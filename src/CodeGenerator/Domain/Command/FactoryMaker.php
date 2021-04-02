<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class FactoryMaker extends AbstractMaker
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractFactory;

/**
 * @method Config getConfig()
 */
final class {$this->className()} extends AbstractFactory
{
}

TEXT;
    }

    protected function className(): string
    {
        return 'Factory';
    }
}
