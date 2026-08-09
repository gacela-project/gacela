<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Container\ValidationProblem;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function class_exists;
use function count;
use function file_exists;
use function implode;
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

        $circularDepsValidation = $this->validateDependencyGraphs($container, $output);
        $hasErrors = $hasErrors || $circularDepsValidation['errors'];
        $hasWarnings = $hasWarnings || $circularDepsValidation['warnings'];

        $hasErrors = $this->validateConfigSchema($output) || $hasErrors;

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
     * The declared configuration schema against this environment's merged
     * values.
     *
     * Reading values constructs nothing, which is what keeps this command
     * side-effect free; a missing key is an error because whatever reads it was
     * going to fail regardless, and the only question is where.
     *
     * @return bool true when the configuration violates its own declaration.
     *              Only errors, no warnings: a declared key is either satisfied
     *              or it is not
     */
    private function validateConfigSchema(OutputInterface $output): bool
    {
        $output->writeln('<comment>Checking configuration schema...</comment>');

        $config = Config::getInstance();
        if ($config->configSchema()->isEmpty()) {
            $output->writeln('  <fg=cyan>No schema declared</fg=cyan>');
            $output->writeln('');

            return false;
        }

        $violations = $config->configSchemaViolations();
        foreach ($violations as $violation) {
            $output->writeln(sprintf('  <error>✗ %s</>', $violation->message));
        }

        if ($violations === []) {
            $output->writeln(sprintf(
                '  <fg=green>✓ %d declared key(s), all satisfied</>',
                count($config->configSchema()->declaredKeys()),
            ));
        }

        $output->writeln('');

        return $violations !== [];
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
                $keyIsType = class_exists($key) || interface_exists($key);

                if (is_string($value)) {
                    if (!class_exists($value)) {
                        $output->writeln(sprintf('  <error>✗ Binding value class does not exist:</> %s -> %s', $key, $value));
                        $hasErrors = true;
                        continue;
                    }

                    if ($keyIsType && !is_subclass_of($value, $key) && $value !== $key) {
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
                    if (!is_callable($value) && $keyIsType && !($value instanceof $key)) {
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
    private function validateDependencyGraphs(Container $container, OutputInterface $output): array
    {
        $output->writeln('<comment>Checking for circular dependencies...</comment>');

        $hasErrors = false;
        $hasWarnings = false;

        foreach ($container->getBindings() as $key => $value) {
            if (!is_string($value)) {
                if (is_callable($value)) {
                    $output->writeln(sprintf(
                        '  <fg=cyan>• Runtime factory not executed; static graph skipped:</> %s',
                        $key,
                    ));
                }

                continue;
            }

            try {
                if (!class_exists($value)) {
                    continue;
                }

                $report = $container->validate([$value]);
                foreach ($report->issues() as $issue) {
                    if ($issue->problem === ValidationProblem::DependencyCycle) {
                        $output->writeln(sprintf('  <error>✗ Circular dependency detected:</> %s', $key));
                        $output->writeln(sprintf(
                            '      chain: %s',
                            implode(' -> ', [...$issue->chain, $issue->class]),
                        ));
                        $hasErrors = true;

                        continue;
                    }

                    $output->writeln(sprintf(
                        '  <fg=yellow>⚠ Warning: Could not resolve binding:</> %s (%s)',
                        $key,
                        $issue->describe(),
                    ));
                    $hasWarnings = true;
                }
            } catch (Throwable $throwable) {
                $output->writeln(sprintf(
                    '  <fg=yellow>⚠ Warning: Could not inspect binding:</> %s (%s)',
                    $key,
                    $throwable->getMessage(),
                ));
                $hasWarnings = true;
            }
        }

        if (!$hasErrors) {
            $output->writeln('  <fg=green>✓ No circular dependencies detected</fg=green>');
        }

        $output->writeln('');

        return ['errors' => $hasErrors, 'warnings' => $hasWarnings];
    }

    /**
     * @param class-string $className the caller has already checked it exists
     */
    private function describeTypeChain(string $className): string
    {
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
    - Accepts class/interface keys and arbitrary service IDs
    - Validates that binding values (classes) exist
    - Checks type compatibility when the key names a class or interface
  - Constructor dependency graphs, without constructing services
  - Runtime factories are reported as outside static graph validation
  - Standalone aliases, lazy registrations, and already-loaded services are outside the binding root set

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
