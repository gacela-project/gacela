<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class DependencyProviderMaker extends AbstractMaker
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\Container\Container;

final class {$this->className()} extends AbstractDependencyProvider
{
    public function provideModuleDependencies(Container \$container): void
    {
    }
}

TEXT;
    }

    protected function className(): string
    {
        return 'DependencyProvider';
    }
}
