<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Domain\FileContent\JsonFile;
use Gacela\Console\Domain\PackageManifest\ComposerPackage;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function dirname;
use function implode;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;
use function str_replace;
use function trim;

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
        // Named after the project's own prefix, so the suggested command is one
        // that works here rather than one that scaffolds into a namespace
        // composer does not map.
        $output->writeln(sprintf(
            'Next: <comment>bin/gacela make:module %s/YourModule --minimal</comment>',
            $this->projectNamespaces()[0] ?? 'App',
        ));

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

        return str_replace('$PROJECT_NAMESPACES$', $this->renderProjectNamespaces(), $template);
    }

    /**
     * The project's own psr-4 prefixes, as a php array literal.
     *
     * This used to be the literal `['App']` for every project, which is right
     * for the projects that use it and quietly wrong for the rest. It is not
     * decoration: the resolver builds `\{projectNamespace}\{Module}\{Module}Factory`
     * from these and tries them *before* the module's own namespace, so a wrong
     * prefix costs a failed lookup on every cold resolution -- and resolves the
     * wrong class outright for a project that does have an `App\` module of the
     * same name.
     */
    private function renderProjectNamespaces(): string
    {
        $prefixes = $this->projectNamespaces();

        if ($prefixes === []) {
            // No manifest, or one that declares no autoloading. `App` is the
            // convention and a better guess than an empty list, which would
            // read as a decision.
            return "['App']";
        }

        return sprintf("['%s']", implode("', '", $prefixes));
    }

    /**
     * @return list<string>
     */
    private function projectNamespaces(): array
    {
        $decoded = JsonFile::decode($this->appRootDir . DIRECTORY_SEPARATOR . 'composer.json');
        if ($decoded === null) {
            return [];
        }

        $namespaces = [];
        foreach (array_keys(ComposerPackage::autoloadPrefixesOf($decoded)) as $prefix) {
            $trimmed = trim($prefix, '\\');
            if ($trimmed !== '') {
                $namespaces[] = $trimmed;
            }
        }

        return $namespaces;
    }
}
