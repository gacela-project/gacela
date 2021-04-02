<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;

abstract class AbstractMaker implements MakerInterface
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

        $path = sprintf('%s/%s.php', $targetDirectory, $this->className());
        $this->io->filePutContents($path, $this->generateFileContent("$rootNamespace\\$moduleName"));

        $this->io->writeln("> Path '$path' created successfully");
    }

    abstract protected function generateFileContent(string $namespace): string;

    abstract protected function className(): string;
}
