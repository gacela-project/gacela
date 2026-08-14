<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Doctor\CheckStatus;
use Gacela\Console\Application\Validate\ValidationFinding;
use Gacela\Console\Application\Validate\ValidationReport;
use Gacela\Console\Application\Validate\ValidationSection;
use Gacela\Container\ValidationProblem;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
use function json_encode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class ValidateConfigCommand extends Command
{
    private const FORMATS = ['text', 'json'];

    private const BINDINGS = 'bindings';

    private const CIRCULAR_DEPENDENCIES = 'circular-dependencies';

    private const CONFIG_SCHEMA = 'config-schema';

    protected function configure(): void
    {
        $this->setName('validate:config')
            ->setDescription('Validate Gacela configuration for errors and best practices')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Exit with a failure code on warnings too, for CI')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Shorthand for --format=json')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = ConsoleInput::format($input);
        $unknownFormat = ConsoleChoice::unknown('format', $format, self::FORMATS);
        if ($unknownFormat !== null) {
            $output->writeln($unknownFormat);

            return Command::FAILURE;
        }

        $report = $this->buildReport();

        if ($format === 'json') {
            $output->writeln(json_encode(
                $report->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));
        } else {
            $this->renderText($output, $report);
        }

        if ($report->hasErrors()) {
            return Command::FAILURE;
        }

        if ($report->hasWarnings()) {
            // The same bargain `doctor --strict` offers: a warning is worth
            // reading but not worth failing a build over, until a project says
            // it is. Without this the only way to act on one was to grep the
            // output, which is what an exit code exists to avoid.
            return $input->getOption('strict') === true ? Command::FAILURE : Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    /**
     * What the run found, before anything decides how to say it. The validators
     * used to write straight to the output and return two booleans, so the
     * messages were the only report there was -- which is why `--json` could
     * not be a flag on top of them.
     */
    private function buildReport(): ValidationReport
    {
        $container = Gacela::container();

        // gacela.php is optional: report it when present, stay silent when missing.
        $gacelaConfigPath = Gacela::rootDir() . '/gacela.php';

        return new ValidationReport(
            [
                $this->validateBindings($container),
                $this->validateDependencyGraphs($container),
                $this->validateConfigSchema(),
            ],
            file_exists($gacelaConfigPath) ? $gacelaConfigPath : '',
        );
    }

    private function renderText(OutputInterface $output, ValidationReport $report): void
    {
        ConsoleSection::title($output, 'Validating Gacela Configuration');

        if ($report->configFile !== '') {
            $output->writeln(sprintf('<fg=green>✓</> Configuration file found: %s', $report->configFile));
            $output->writeln('');
        }

        foreach ($report->sections as $section) {
            $this->renderSection($output, $section);
        }

        $output->writeln('');
        ConsoleSection::separator($output);

        if ($report->hasErrors()) {
            $output->writeln('<error>✗ Validation failed with errors</error>');
            $output->writeln('');

            return;
        }

        if ($report->hasWarnings()) {
            $output->writeln('<fg=yellow>⚠ Validation completed with warnings</fg=yellow>');
            $output->writeln('');

            return;
        }

        $output->writeln('<fg=green>✓ Configuration is valid!</fg=green>');
        $output->writeln('');
    }

    private function renderSection(OutputInterface $output, ValidationSection $section): void
    {
        $output->writeln(sprintf('<comment>%s</comment>', $section->title));

        if ($section->summary !== '') {
            $output->writeln('  ' . $section->summary);
            $output->writeln('');
        }

        foreach ($section->findings as $finding) {
            $output->writeln($finding->render());

            foreach ($finding->details as $detail) {
                $output->writeln('      ' . $detail);
            }
        }

        $output->writeln('');
    }

    private function validateBindings(Container $container): ValidationSection
    {
        $findings = [];
        $summary = '';

        try {
            $bindings = $container->getBindings();

            if ($bindings === []) {
                return new ValidationSection(self::BINDINGS, 'Checking bindings...', '', [
                    ValidationFinding::note('No bindings configured'),
                ]);
            }

            $summary = sprintf(
                'Found %d binding%s',
                count($bindings),
                count($bindings) === 1 ? '' : 's',
            );

            foreach ($bindings as $key => $value) {
                $findings[] = $this->inspectBinding($key, $value);
            }
        } catch (Throwable $throwable) {
            $findings[] = ValidationFinding::failure(
                sprintf('Error validating bindings: %s', $throwable->getMessage()),
            );
        }

        return new ValidationSection(self::BINDINGS, 'Checking bindings...', $summary, $findings);
    }

    private function inspectBinding(string $key, mixed $value): ValidationFinding
    {
        $keyIsType = class_exists($key) || interface_exists($key);

        if (is_string($value)) {
            if (!class_exists($value)) {
                return ValidationFinding::error(
                    'Binding value class does not exist',
                    sprintf('%s -> %s', $key, $value),
                );
            }

            if ($keyIsType && !is_subclass_of($value, $key) && $value !== $key) {
                return ValidationFinding::warning(
                    'Warning: Binding value may not be compatible with key',
                    sprintf('%s -> %s', $key, $value),
                    [
                        sprintf('expected %s: %s', interface_exists($key) ? 'interface' : 'class', $key),
                        sprintf('actual:       %s', $this->describeTypeChain($value)),
                        sprintf('hint:         make %s extend or implement %s', $value, $key),
                    ],
                );
            }
        } elseif (is_object($value)
            // Callable objects (factories) are always valid; other objects must be instances of the key.
            && !is_callable($value) && $keyIsType && !($value instanceof $key)
        ) {
            return ValidationFinding::error('Binding object is not instance of key', $key);
        }

        // The tick means this binding had nothing to report, so it is not
        // printed beside a warning about the same key -- that reads as the
        // warning being withdrawn.
        return ValidationFinding::ok('', $key);
    }

    private function validateDependencyGraphs(Container $container): ValidationSection
    {
        $findings = [];

        foreach ($container->getBindings() as $key => $value) {
            if (!is_string($value)) {
                if (is_callable($value)) {
                    $findings[] = ValidationFinding::info(
                        'Runtime factory not executed; static graph skipped',
                        $key,
                    );
                }

                continue;
            }

            foreach ($this->inspectGraph($container, $key, $value) as $finding) {
                $findings[] = $finding;
            }
        }

        if (!$this->anyError($findings)) {
            $findings[] = ValidationFinding::ok('No circular dependencies detected');
        }

        return new ValidationSection(
            self::CIRCULAR_DEPENDENCIES,
            'Checking for circular dependencies...',
            '',
            $findings,
        );
    }

    /**
     * @return list<ValidationFinding>
     */
    private function inspectGraph(Container $container, string $key, string $value): array
    {
        $findings = [];

        try {
            if (!class_exists($value)) {
                return [];
            }

            foreach ($container->validate([$value])->issues() as $issue) {
                if ($issue->problem === ValidationProblem::DependencyCycle) {
                    $findings[] = ValidationFinding::error('Circular dependency detected', $key, [
                        sprintf('chain: %s', implode(' -> ', [...$issue->chain, $issue->class])),
                    ]);

                    continue;
                }

                $findings[] = ValidationFinding::warning(
                    'Warning: Could not resolve binding',
                    sprintf('%s (%s)', $key, $issue->describe()),
                );
            }
        } catch (Throwable $throwable) {
            $findings[] = ValidationFinding::warning(
                'Warning: Could not inspect binding',
                sprintf('%s (%s)', $key, $throwable->getMessage()),
            );
        }

        return $findings;
    }

    /**
     * The declared configuration schema against this environment's merged
     * values.
     *
     * Reading values constructs nothing, which is what keeps this command
     * side-effect free; a missing key is an error because whatever reads it was
     * going to fail regardless, and the only question is where. Only errors, no
     * warnings: a declared key is either satisfied or it is not.
     */
    private function validateConfigSchema(): ValidationSection
    {
        $title = 'Checking configuration schema...';
        $config = Config::getInstance();

        if ($config->configSchema()->isEmpty()) {
            return new ValidationSection(self::CONFIG_SCHEMA, $title, '', [
                ValidationFinding::note('No schema declared'),
            ]);
        }

        $violations = $config->configSchemaViolations();

        if ($violations === []) {
            return new ValidationSection(self::CONFIG_SCHEMA, $title, '', [
                ValidationFinding::ok(sprintf(
                    '%d declared key(s), all satisfied',
                    count($config->configSchema()->declaredKeys()),
                )),
            ]);
        }

        $findings = [];
        foreach ($violations as $violation) {
            $findings[] = ValidationFinding::error($violation->message);
        }

        return new ValidationSection(self::CONFIG_SCHEMA, $title, '', $findings);
    }

    /**
     * @param list<ValidationFinding> $findings
     */
    private function anyError(array $findings): bool
    {
        foreach ($findings as $finding) {
            if ($finding->status === CheckStatus::Error) {
                return true;
            }
        }

        return false;
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

  # Report it as a document, for a job that has to act on which check failed
  bin/gacela validate:config --json

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
