<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

final class ConfigMaker extends AbstractMaker
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractConfig;

final class {$this->className()} extends AbstractConfig
{
}

TEXT;
    }

    protected function className(): string
    {
        return 'Config';
    }
}
