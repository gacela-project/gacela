<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\MakerIoInterface;
use Gacela\CodeGenerator\Domain\ReadModel\CommandArguments;

final class ModuleMaker implements MakerInterface
{
    private MakerIoInterface $io;

    /** @var MakerInterface[] */
    private array $generators;

    /**
     * @param MakerInterface[] $generators
     */
    public function __construct(MakerIoInterface $io, array $generators)
    {
        $this->io = $io;
        $this->generators = $generators;
    }

    public function make(CommandArguments $commandArguments): void
    {
        foreach ($this->generators as $generator) {
            $generator->make($commandArguments);
        }

        $pieces = explode('/', $commandArguments->directory());
        $moduleName = end($pieces);
        $this->io->writeln("Module $moduleName created successfully");
    }
}
