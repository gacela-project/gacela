<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Domain\ModuleVersion\ModuleVersionParserInterface;
use Gacela\Console\Domain\ModuleVersion\VersionCompatibilityChecker;
use Gacela\Console\Infrastructure\ModuleVersion\ArrayModuleVersionParser;
use Gacela\Console\Infrastructure\ModuleVersion\YamlModuleVersionParser;
use Gacela\Framework\Gacela;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use Throwable;

use function count;
use function file_exists;
use function sprintf;

final class VersionCheckCommand extends Command
{
    private const DEFAULT_YAML_FILE = 'gacela-versions.yaml';
    private const DEFAULT_PHP_FILE = 'gacela-versions.php';

    protected function configure(): void
    {
        $this->setName('version:check')
            ->setDescription('Check module version compatibility from gacela-versions.yaml or gacela-versions.php')
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_REQUIRED,
                'Path to version file (defaults to gacela-versions.yaml or gacela-versions.php)',
            )
            ->addOption(
                'detailed',
                'd',
                InputOption::VALUE_NONE,
                'Show detailed module version information',
            )
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Module Version Compatibility Check</info>');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $output->writeln('');

        $filePath = $this->resolveVersionFilePath($input, $output);

        if ($filePath === null) {
            $output->writeln('<error>No version file found</error>');
            $output->writeln('');
            $output->writeln('Create one of the following files:');
            $output->writeln(sprintf('  - %s/%s (requires YAML parser)', Gacela::rootDir(), self::DEFAULT_YAML_FILE));
            $output->writeln(sprintf('  - %s/%s (PHP array format)', Gacela::rootDir(), self::DEFAULT_PHP_FILE));
            $output->writeln('');
            $output->writeln('Run with --help to see example formats');
            $output->writeln('');

            return self::FAILURE;
        }

        try {
            $parser = $this->getParser($filePath);
            $moduleVersions = $parser->parseVersionsFile($filePath);

            if ($moduleVersions === []) {
                $output->writeln('<fg=yellow>No modules defined in version file</fg=yellow>');
                $output->writeln('');

                return self::SUCCESS;
            }

            $output->writeln(sprintf('<fg=green>✓</> Loaded version file: %s', $filePath));
            $output->writeln(sprintf('  Found %d module%s', count($moduleVersions), count($moduleVersions) === 1 ? '' : 's'));
            $output->writeln('');

            if ((bool)$input->getOption('detailed')) {
                $this->displayDetailedVersions($moduleVersions, $output);
            }

            $checker = new VersionCompatibilityChecker($moduleVersions);
            $result = $checker->checkCompatibility();

            if ($result->errors !== []) {
                $output->writeln('<error>Compatibility Errors:</error>');
                foreach ($result->errors as $error) {
                    $output->writeln(sprintf('  <error>✗</error> %s', $error));
                }
                $output->writeln('');
            }

            if ($result->warnings !== []) {
                $output->writeln('<fg=yellow>Warnings:</fg=yellow>');
                foreach ($result->warnings as $warning) {
                    $output->writeln(sprintf('  <fg=yellow>⚠</fg=yellow> %s', $warning));
                }
                $output->writeln('');
            }

            $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));

            if ($result->isCompatible) {
                $output->writeln('<fg=green>✓ All module versions are compatible!</fg=green>');
                $output->writeln('');

                return self::SUCCESS;
            }

            $output->writeln('<error>✗ Version compatibility check failed</error>');
            $output->writeln('');

            return self::FAILURE;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>Error: %s</error>', $throwable->getMessage()));
            $output->writeln('');

            return self::FAILURE;
        }
    }

    private function resolveVersionFilePath(InputInterface $input, OutputInterface $output): ?string
    {
        $customPath = $input->getOption('file');

        if ($customPath !== null && $customPath !== '') {
            if (!file_exists((string)$customPath)) {
                $output->writeln(sprintf('<error>Specified file not found: %s</error>', $customPath));
                $output->writeln('');

                return null;
            }

            return (string)$customPath;
        }

        // Try default YAML file first
        $yamlPath = Gacela::rootDir() . '/' . self::DEFAULT_YAML_FILE;
        if (file_exists($yamlPath)) {
            return $yamlPath;
        }

        // Try default PHP file
        $phpPath = Gacela::rootDir() . '/' . self::DEFAULT_PHP_FILE;
        if (file_exists($phpPath)) {
            return $phpPath;
        }

        return null;
    }

    private function getParser(string $filePath): ModuleVersionParserInterface
    {
        // Try YAML parser first if file ends with .yaml or .yml
        if (str_ends_with($filePath, '.yaml') || str_ends_with($filePath, '.yml')) {
            $yamlParser = new YamlModuleVersionParser();
            if ($yamlParser->isAvailable()) {
                return $yamlParser;
            }
        }

        // Fallback to array parser for .php files
        return new ArrayModuleVersionParser();
    }

    /**
     * @param array<string, \Gacela\Console\Domain\ModuleVersion\TModuleVersion> $moduleVersions
     */
    private function displayDetailedVersions(array $moduleVersions, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setStyle('box');
        $table->setHeaders(['Module', 'Version', 'Requires']);

        foreach ($moduleVersions as $module) {
            $requires = [];
            foreach ($module->requiredModules as $name => $version) {
                $requires[] = sprintf('%s: %s', $name, $version);
            }

            $table->addRow([
                $module->moduleName,
                $module->version,
                $requires !== [] ? implode("\n", $requires) : '-',
            ]);
        }

        $table->render();
        $output->writeln('');
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command validates module version compatibility by checking the dependencies
declared in gacela-versions.yaml (or gacela-versions.php).

<info>Configuration file format (YAML):</info>

Create a file named <comment>gacela-versions.yaml</comment> in your project root:

```yaml
User:
  version: "1.2.0"
  requires:
    Auth: "^1.0"

Product:
  version: "2.0.0"
  requires:
    User: "^1.0"
    Catalog: "~2.0"

Auth:
  version: "1.5.0"
```

<info>Configuration file format (PHP):</info>

Alternatively, create <comment>gacela-versions.php</comment> in your project root:

```php
<?php

return [
    'User' => [
        'version' => '1.2.0',
        'requires' => [
            'Auth' => '^1.0',
        ],
    ],
    'Product' => [
        'version' => '2.0.0',
        'requires' => [
            'User' => '^1.0',
            'Catalog' => '~2.0',
        ],
    ],
    'Auth' => [
        'version' => '1.5.0',
    ],
];
```

<info>Supported version constraints:</info>
  <comment>^1.0</comment>   - Caret: >= 1.0.0 and < 2.0.0
  <comment>~1.2</comment>   - Tilde: >= 1.2.0 and < 1.3.0
  <comment>>=1.5</comment>  - Greater than or equal
  <comment>>1.5</comment>   - Greater than
  <comment><=2.0</comment>  - Less than or equal
  <comment><2.0</comment>   - Less than
  <comment>1.2.0</comment>  - Exact version

<info>Examples:</info>
  # Check version compatibility (auto-detect file)
  bin/gacela version:check

  # Check specific file
  bin/gacela version:check --file=custom-versions.yaml

  # Show detailed module information
  bin/gacela version:check --detailed

<info>Exit codes:</info>
  0 - All versions compatible
  1 - Incompatible versions found or file error
HELP;
    }
}
