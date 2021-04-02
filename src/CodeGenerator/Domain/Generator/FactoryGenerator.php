<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;

final class FactoryGenerator implements GeneratorInterface
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

        $path = sprintf('%s/%sFactory.php', $targetDirectory, $moduleName);
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

use Gacela\Framework\AbstractFactory;

/**
 * @see https://github.com/gacela-project/gacela/blob/master/docs/003_factory.md
 *
 * @method {$moduleName}Config getConfig()
 */
final class {$moduleName}Factory extends AbstractFactory
{
}
TEXT;
    }
}
