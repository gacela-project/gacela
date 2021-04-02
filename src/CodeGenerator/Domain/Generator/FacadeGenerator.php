<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

final class FacadeGenerator extends AbstractGenerator
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractFacade;

/**
 * @see https://github.com/gacela-project/gacela/blob/master/docs/002_facade.md
 *
 * @method Factory getFactory()
 */
final class {$this->classType()} extends AbstractFacade
{
}
TEXT;
    }

    protected function classType(): string
    {
        return 'Facade';
    }
}
