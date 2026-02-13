<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function file_put_contents;
use function sprintf;

/**
 * @method ConsoleFacade getFacade()
 */
final class GenerateIdeHelperCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('generate:ide-helper')
            ->setDescription('Generate IDE helper files for better autocomplete')
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path',
                '.phpstorm.meta.php',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputFile = (string)$input->getOption('output');

        $output->writeln('<info>Generating IDE helper files...</info>');

        $modules = $this->getFacade()->findAllAppModules();

        if (count($modules) === 0) {
            $output->writeln('<comment>No modules found. Skipping IDE helper generation.</comment>');

            return self::SUCCESS;
        }

        $metaContent = $this->getFacade()->generateIdeHelperMeta($modules);

        file_put_contents($outputFile, $metaContent);

        $output->writeln(sprintf('<info>IDE helper generated successfully: %s</info>', $outputFile));
        $output->writeln(sprintf('<comment>Found %d module(s) for autocomplete support.</comment>', count($modules)));

        return self::SUCCESS;
    }
}
