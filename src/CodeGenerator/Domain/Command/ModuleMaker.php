<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\MakerIoInterface;

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

    public function make(string $rootNamespace, string $targetDirectory): void
    {
        foreach ($this->generators as $generator) {
            $generator->make($rootNamespace, $targetDirectory);
        }

        $pieces = explode('/', $targetDirectory);
        $moduleName = end($pieces);
        $this->io->writeln("Module $moduleName created successfully");
    }
}
