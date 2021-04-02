<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

final class ConfigGenerator extends AbstractGenerator
{
    protected function generateFileContent(string $namespace): string
    {
        return <<<TEXT
<?php

declare(strict_types=1);

namespace {$namespace};

use Gacela\Framework\AbstractConfig;

/**
 * @see https://github.com/gacela-project/gacela/blob/master/docs/004_config.md
 *
 * Remember to placed this in the entry point of your application:
 * Config::setApplicationRootDir(realpath(__DIR__));
 */
final class {$this->classType()} extends AbstractConfig
{
}

TEXT;
    }

    protected function classType(): string
    {
        return 'Config';
    }
}
