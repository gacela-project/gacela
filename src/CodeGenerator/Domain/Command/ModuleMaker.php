<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Command;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;

final class ModuleMaker implements MakerInterface
{
    private GeneratorIoInterface $io;

    /** @var MakerInterface[] */
    private array $generators;

    /**
     * @param MakerInterface[] $generators
     */
    public function __construct(GeneratorIoInterface $io, array $generators)
    {
        $this->io = $io;
        $this->generators = $generators;
    }

    public function generate(string $rootNamespace, string $targetDirectory): void
    {
        foreach ($this->generators as $generator) {
            $generator->generate($rootNamespace, $targetDirectory);
        }

        $pieces = explode('/', $targetDirectory);
        $moduleName = end($pieces);
        $this->io->writeln("Module $moduleName created successfully");
    }
}
