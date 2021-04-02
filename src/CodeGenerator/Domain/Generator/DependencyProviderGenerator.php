<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

final class DependencyProviderGenerator extends AbstractGenerator
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

/**
 * @see https://github.com/gacela-project/gacela/blob/master/docs/005_dependency_provider.md
 */
final class {$this->classType()} extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container \$container): void
    {
    }
}

TEXT;
    }

    protected function classType(): string
    {
        return 'DependencyProvider';
    }
}
