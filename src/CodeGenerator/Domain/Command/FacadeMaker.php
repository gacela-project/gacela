<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class FacadeMaker extends AbstractMaker
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 */
final class {$this->className()} extends AbstractFacade
{
}

TEXT;
    }

    protected function className(): string
    {
        return 'Facade';
    }
}
