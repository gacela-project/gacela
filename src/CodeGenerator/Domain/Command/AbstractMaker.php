<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\MakerIoInterface;

abstract class AbstractMaker implements MakerInterface
{
    private MakerIoInterface $io;

    public function __construct(MakerIoInterface $io)
    {
        $this->io = $io;
    }

    public function make(string $rootNamespace, string $targetDirectory): void
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
