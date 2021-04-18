<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\MakerIoInterface;
use Gacela\CodeGenerator\Domain\ReadModel\CommandArguments;

abstract class AbstractMaker implements MakerInterface
{
    private MakerIoInterface $io;
    private string $template;

    public function __construct(MakerIoInterface $io, string $template)
    {
        $this->template = $template;
        $this->io = $io;
    }

    public function make(CommandArguments $commandArguments): void
    {
        $this->io->createDirectory($commandArguments->directory());

        $path = sprintf('%s/%s.php', $commandArguments->directory(), $this->className());
        $this->io->filePutContents($path, $this->generateFileContent($commandArguments->namespace()));

        $this->io->writeln("> Path '$path' created successfully");
    }

    abstract protected function className(): string;

    private function generateFileContent(string $namespace): string
    {
        $search = ['$NAMESPACE$', '$CLASS_NAME$'];
        $replace = [$namespace, $this->className()];

        return str_replace($search, $replace, $this->template);
    }
}
