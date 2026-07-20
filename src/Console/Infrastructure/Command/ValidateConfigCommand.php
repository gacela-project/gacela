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

/**
 * @psalm-type ValidationResult = array{errors: bool, warnings: bool}
 */
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
        ConsoleSection::title($output, 'Validating Gacela Configuration');

        // gacela.php is optional: report it when present, stay silent when missing.
        $gacelaConfigPath = Gacela::rootDir() . '/gacela.php';
        if (file_exists($gacelaConfigPath)) {
            $output->writeln(sprintf('<fg=green>✓</> Configuration file found: %s', $gacelaConfigPath));
            $output->writeln('');
        }

        $container = Gacela::container();
        $bindingsValidation = $this->validateBindings($container, $output);
        $hasErrors = $bindingsValidation['errors'];
        $hasWarnings = $bindingsValidation['warnings'];

        $circularDepsValidation = $this->checkCircularDependencies($container, $output);
        $hasErrors = $hasErrors || $circularDepsValidation['errors'];
        $hasWarnings = $hasWarnings || $circularDepsValidation['warnings'];

        $output->writeln('');
        ConsoleSection::separator($output);

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
     * @return ValidationResult
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
                if (!class_exists($key) && !interface_exists($key)) {
                    $output->writeln(sprintf('  <error>✗ Binding key does not exist:</> %s', $key));
                    $hasErrors = true;
                    continue;
                }

                if (is_string($value)) {
                    if (!class_exists($value)) {
                        $output->writeln(sprintf('  <error>✗ Binding value class does not exist:</> %s -> %s', $key, $value));
                        $hasErrors = true;
                        continue;
                    }

                    if (!is_subclass_of($value, $key) && $value !== $key) {
                        $expectedKind = interface_exists($key) ? 'interface' : 'class';
                        $valueParents = $this->describeTypeChain($value);

                        $output->writeln(sprintf(
                            '  <fg=yellow>⚠ Warning: Binding value may not be compatible with key:</> %s -> %s',
                            $key,
                            $value,
                        ));
                        $output->writeln(sprintf('      expected %s: %s', $expectedKind, $key));
                        $output->writeln(sprintf('      actual:       %s', $valueParents));
                        $output->writeln(sprintf('      hint:         make %s extend or implement %s', $value, $key));
                        $hasWarnings = true;
                    }
                } elseif (is_object($value)) {
                    // Callable objects (factories) are always valid; other objects must be instances of the key.
                    if (!is_callable($value) && class_exists($key) && !($value instanceof $key)) {
                        $output->writeln(sprintf('  <error>✗ Binding object is not instance of key:</> %s', $key));
                        $hasErrors = true;
                        continue;
                    }
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
     * @return ValidationResult
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

                if (class_exists($value)) {
                    try {
                        // Resolving the binding surfaces circular/recursive dependency errors.
                        $container->get($key);
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

    private function describeTypeChain(string $className): string
    {
        if (!class_exists($className) && !interface_exists($className)) {
            return $className;
        }

        $parents = class_parents($className) ?: [];
        $interfaces = class_implements($className) ?: [];

        $parts = [$className];
        if ($parents !== []) {
            $parts[] = 'extends ' . implode(' -> ', $parents);
        }

        if ($interfaces !== []) {
            $parts[] = 'implements ' . implode(', ', $interfaces);
        }

        return implode(' | ', $parts);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command validates your Gacela configuration for common errors and potential issues.

<info>What it checks:</info>
  - Presence of the optional gacela.php file (its absence is not an error; it is only reported when found)
  - Bindings configuration:
    - Validates that binding keys (interfaces/classes) exist
    - Validates that binding values (classes) exist
    - Checks type compatibility between keys and values
  - Circular dependency detection (basic check)

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
