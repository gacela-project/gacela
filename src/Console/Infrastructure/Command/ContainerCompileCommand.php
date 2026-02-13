<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function file_put_contents;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class ContainerCompileCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('container:compile')
            ->setDescription('Compile container for production optimization')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path',
                'var/cache/container_compiled.php',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = (string)$input->getOption('output');

        $output->writeln('<info>Compiling container...</info>');

        $compiledCode = $this->getFacade()->compileContainer();

        $outputDir = dirname($outputFile);
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        file_put_contents($outputFile, $compiledCode);

        $output->writeln(sprintf('<info>Container compiled successfully to: %s</info>', $outputFile));
        $output->writeln('<comment>Use this file in production to optimize container initialization.</comment>');

        return self::SUCCESS;
    }
}
