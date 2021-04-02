<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

final class FactoryGenerator extends AbstractGenerator
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractFactory;

/**
 * @see https://github.com/gacela-project/gacela/blob/master/docs/003_factory.md
 *
 * @method Config getConfig()
 */
final class {$this->classType()} extends AbstractFactory
{
}

TEXT;
    }

    protected function classType(): string
    {
        return 'Factory';
    }
}
