<?php

declare(strict_types=1);

namespace Gacela\CodeGenerator\Domain\Generator;

use Gacela\CodeGenerator\Domain\Io\GeneratorIoInterface;

final class ModuleGenerator
{
    private GeneratorIoInterface $io;

    /** @var GeneratorInterface[] */
    private array $generators;

    /**
     * @param GeneratorInterface[] $generators
     */
    public function __construct(GeneratorIoInterface $io, array $generators)
    {
        $this->generators = $generators;
        $this->io = $io;
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
