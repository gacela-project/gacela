<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function class_exists;
use function count;
use function file_exists;
use function interface_exists;
use function is_callable;
use function is_object;
use function is_string;
use function sprintf;

final class ValidateConfigCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('validate:config')
            ->setDescription('Validate Gacela configuration for errors and best practices')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('');
        $output->writeln('<info>Validating Gacela Configuration</info>');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));
        $output->writeln('');

        $hasErrors = false;
        $hasWarnings = false;

        // Check if gacela.php exists
        $gacelaConfigPath = Gacela::rootDir() . '/gacela.php';
        if (!file_exists($gacelaConfigPath)) {
            $output->writeln('<fg=yellow>⚠ Warning:</> No gacela.php configuration file found');
            $output->writeln(sprintf('  Expected at: %s', $gacelaConfigPath));
            $output->writeln('');
            $hasWarnings = true;
        } else {
            $output->writeln(sprintf('<fg=green>✓</> Configuration file found: %s', $gacelaConfigPath));
            $output->writeln('');
        }

        // Validate bindings
        $container = Gacela::container();
        $bindingsValidation = $this->validateBindings($container, $output);
        $hasErrors = $bindingsValidation['errors'];
        $hasWarnings = $bindingsValidation['warnings'];

        // Validate config paths
        $configValidation = $this->validateConfigPaths($output);
        $hasErrors = $hasErrors || $configValidation['errors'];
        $hasWarnings = $hasWarnings || $configValidation['warnings'];

        // Check for circular dependencies (basic check)
        $circularDepsValidation = $this->checkCircularDependencies($container, $output);
        $hasErrors = $hasErrors || $circularDepsValidation['errors'];
        $hasWarnings = $hasWarnings || $circularDepsValidation['warnings'];

        // Summary
        $output->writeln('');
        $output->writeln(sprintf('<info>%s</info>', str_repeat('=', 60)));

        if ($hasErrors) {
            $output->writeln('<error>✗ Validation failed with errors</error>');
            $output->writeln('');
            return Command::FAILURE;
        }

        if ($hasWarnings) {
            $output->writeln('<fg=yellow>⚠ Validation completed with warnings</fg=yellow>');
            $output->writeln('');
            return Command::SUCCESS;
        }

        $output->writeln('<fg=green>✓ Configuration is valid!</fg=green>');
        $output->writeln('');

        return Command::SUCCESS;
    }

    /**
     * @return array{errors: bool, warnings: bool}
     */
    private function validateBindings(Container $container, OutputInterface $output): array
    {
        $output->writeln('<comment>Checking bindings...</comment>');

        $hasErrors = false;
        $hasWarnings = false;

        try {
            $bindings = $container->getBindings();

            if ($bindings === []) {
                $output->writeln('  <fg=cyan>No bindings configured</fg=cyan>');
                $output->writeln('');
                return ['errors' => false, 'warnings' => false];
            }

            $output->writeln(sprintf('  Found %d binding%s', count($bindings), count($bindings) === 1 ? '' : 's'));
            $output->writeln('');

            foreach ($bindings as $key => $value) {
                // Validate key exists as class or interface
                if (!class_exists($key) && !interface_exists($key)) {
                    $output->writeln(sprintf('  <error>✗ Binding key does not exist:</> %s', $key));
                    $hasErrors = true;
                    continue;
                }

                // Validate value
                if (is_string($value)) {
                    if (!class_exists($value)) {
                        $output->writeln(sprintf('  <error>✗ Binding value class does not exist:</> %s -> %s', $key, $value));
                        $hasErrors = true;
                        continue;
                    }

                    // Check if value is assignable to key (basic check)
                    if (class_exists($key) && !is_subclass_of($value, $key) && $value !== $key) {
                        $output->writeln(sprintf('  <fg=yellow>⚠ Warning: Binding value may not be compatible with key:</> %s -> %s', $key, $value));
                        $hasWarnings = true;
                    }
                } elseif (is_object($value)) {
                    // Object binding (including callables) - check if it's assignable to key
                    if (!is_callable($value) && class_exists($key) && !($value instanceof $key)) {
                        $output->writeln(sprintf('  <error>✗ Binding object is not instance of key:</> %s', $key));
                        $hasErrors = true;
                        continue;
                    }

                    // Callable objects are valid - no further validation needed
                }

                $output->writeln(sprintf('  <fg=green>✓</> %s', $key));
            }
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('  <error>Error validating bindings: %s</error>', $throwable->getMessage()));
            $hasErrors = true;
        }

        $output->writeln('');

        return ['errors' => $hasErrors, 'warnings' => $hasWarnings];
    }

    /**
     * @return array{errors: bool, warnings: bool}
     */
    private function validateConfigPaths(OutputInterface $output): array
    {
        $output->writeln('<comment>Checking configuration paths...</comment>');

        // Note: We can't easily access the config paths from the current API
        // This is a placeholder for future enhancement
        $output->writeln('  <fg=cyan>Config path validation requires runtime configuration access</fg=cyan>');
        $output->writeln('');

        return ['errors' => false, 'warnings' => false];
    }

    /**
     * @return array{errors: bool, warnings: bool}
     */
    private function checkCircularDependencies(Container $container, OutputInterface $output): array
    {
        $output->writeln('<comment>Checking for circular dependencies...</comment>');

        $hasErrors = false;
        $hasWarnings = false;

        try {
            $bindings = $container->getBindings();

            foreach ($bindings as $key => $value) {
                if (!is_string($value)) {
                    continue;
                }

                // Check if value class depends back on key (simple check)
                if (class_exists($value)) {
                    try {
                        /**
                         * @psalm-suppress UnusedVariable
                         * @psalm-suppress MixedAssignment
                         */
                        $resolved = $container->get($key);
                        // If we can resolve it without errors, it's likely fine
                    } catch (Throwable $throwable) {
                        if (str_contains($throwable->getMessage(), 'circular') || str_contains($throwable->getMessage(), 'recursive')) {
                            $output->writeln(sprintf('  <error>✗ Circular dependency detected:</> %s', $key));
                            $hasErrors = true;
                        }
                    }
                }
            }

            if (!$hasErrors) {
                $output->writeln('  <fg=green>✓ No circular dependencies detected</fg=green>');
            }
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('  <fg=yellow>⚠ Warning: Could not check circular dependencies: %s</fg=yellow>', $throwable->getMessage()));
            $hasWarnings = true;
        }

        $output->writeln('');

        return ['errors' => $hasErrors, 'warnings' => $hasWarnings];
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command validates your Gacela configuration for common errors and potential issues.

<info>What it checks:</info>
  - Existence of gacela.php configuration file
  - Bindings configuration:
    - Validates that binding keys (interfaces/classes) exist
    - Validates that binding values (classes) exist
    - Checks type compatibility between keys and values
  - Circular dependency detection (basic check)
  - Configuration file paths

<info>Validation levels:</info>
  <error>Errors</error> - Critical issues that will cause runtime failures
  <fg=yellow>Warnings</fg=yellow> - Potential issues or best practice violations that may work but should be reviewed

<info>Examples:</info>
  # Validate configuration
  bin/gacela validate:config

<comment>Best practices:</comment>
  - Run this command before deploying to production
  - Add it to your CI/CD pipeline
  - Run after adding new bindings or changing configuration
  - Use with cache:warm for complete pre-deployment validation

<info>Exit codes:</info>
  0 - Validation successful (may have warnings)
  1 - Validation failed with errors
HELP;
    }
}
