<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function is_file;
use function sprintf;

/**
 * Scaffolds the `gacela.php` a project needs before anything else works.
 *
 * Takes the app root as a constructor argument rather than reading it from a
 * bootstrapped Config: this command runs *before* the project has a gacela.php,
 * so there is nothing to bootstrap from yet.
 */
final class InitCommand extends Command
{
    private const FILENAME = 'gacela.php';

    private const TEMPLATE = __DIR__ . '/../Template/Command/gacela-php.txt';

    public function __construct(
        private readonly string $appRootDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('init')
            ->setDescription('Create a gacela.php config file in the project root')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite an existing gacela.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $target = $this->appRootDir . DIRECTORY_SEPARATOR . self::FILENAME;

        if (is_file($target) && $input->getOption('force') !== true) {
            $output->writeln(sprintf('<error>%s already exists.</error>', self::FILENAME));
            $output->writeln('Pass <comment>--force</comment> to overwrite it.');

            return self::FAILURE;
        }

        if (file_put_contents($target, $this->readTemplate()) === false) {
            throw new RuntimeException(sprintf('File "%s" was not written', $target));
        }

        $output->writeln(sprintf('<fg=green>✓</> Created %s', $target));
        $output->writeln('');
        $output->writeln('Next: <comment>bin/gacela make:module App/YourModule --minimal</comment>');

        return self::SUCCESS;
    }

    /**
     * The template ships with the package, so an unreadable one means a broken
     * installation rather than anything the caller did.
     */
    private function readTemplate(): string
    {
        $template = file_get_contents(self::TEMPLATE);
        if ($template === false) {
            throw new RuntimeException(sprintf('Template "%s" could not be read', self::TEMPLATE));
        }

        return $template;
    }
}
