<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;

final class ConfigGenerator implements GeneratorInterface
{
    private GeneratorIoInterface $io;

    public function __construct(GeneratorIoInterface $io)
    {
        $this->io = $io;
    }

    public function generate(string $rootNamespace, string $targetDirectory): void
    {
        $pieces = explode('/', $targetDirectory);
        $moduleName = end($pieces);

        $this->io->createDirectory($targetDirectory);

        $path = sprintf('%s/%sConfig.php', $targetDirectory, $moduleName);
        $this->io->filePutContents($path, $this->generateFileContent($rootNamespace, $moduleName));

        $this->io->writeln("> Path $path created successfully");
    }

    private function generateFileContent(string $rootNamespace, string $moduleName): string
    {
        $namespace = "$rootNamespace\\$moduleName";

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
final class {$moduleName}Config extends AbstractConfig
{
}
TEXT;
    }
}
