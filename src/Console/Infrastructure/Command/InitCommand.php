<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function dirname;
use function is_dir;
use function is_file;
use function mkdir;
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

    /**
     * The generated `gacela.php` declares `config/*.php`, so the directory has
     * to exist with something in it -- otherwise the very first `doctor` run on
     * a freshly scaffolded project reports a config path that loads nothing,
     * which is true and entirely the scaffolder's doing.
     */
    private const CONFIG_FILENAME = 'config' . DIRECTORY_SEPARATOR . 'app.php';

    private const CONFIG_TEMPLATE = __DIR__ . '/../Template/Command/app-config.txt';

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

        $configTarget = $this->writeAppConfig();
        if ($configTarget !== null) {
            $output->writeln(sprintf('<fg=green>✓</> Created %s', $configTarget));
        }

        $output->writeln('');
        $output->writeln('Next: <comment>bin/gacela make:module App/YourModule --minimal</comment>');

        return self::SUCCESS;
    }

    /**
     * The config file the generated `gacela.php` points at.
     *
     * Never overwritten, not even with `--force`: that flag is about replacing
     * a `gacela.php` you asked to regenerate, and the configuration next to it
     * is yours. Returns null when there was already one, so nothing is claimed
     * to have been created.
     */
    private function writeAppConfig(): ?string
    {
        $target = $this->appRootDir . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;

        if (is_file($target)) {
            return null;
        }

        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $template = file_get_contents(self::CONFIG_TEMPLATE);
        if ($template === false) {
            throw new RuntimeException(sprintf('Template "%s" could not be read', self::CONFIG_TEMPLATE));
        }

        if (file_put_contents($target, $template) === false) {
            throw new RuntimeException(sprintf('File "%s" was not written', $target));
        }

        return $target;
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
