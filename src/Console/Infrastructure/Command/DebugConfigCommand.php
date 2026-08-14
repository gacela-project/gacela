<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Config\Config;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function count;
use function is_bool;

use function is_scalar;

use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DebugConfigCommand extends Command
{
    use ServiceResolverAwareTrait;

    private const SCHEMA_DECLARED = 'declared';

    private const SCHEMA_UNDECLARED = 'undeclared';

    private const SCHEMA_MISSING = 'missing';

    protected function configure(): void
    {
        $this->setName('debug:config')
            ->setDescription('Show the effective merged configuration')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Only show keys containing this substring')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: text|json', 'text')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Shorthand for --format=json')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = ConsoleInput::argument($input, 'filter');
        $entries = $this->collect($filter);

        if (ConsoleInput::format($input) === 'json') {
            $output->writeln(json_encode(
                $entries,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($entries as $entry) {
            $rows[] = $entry['schema'] === self::SCHEMA_MISSING
                ? [$entry['key'], '<comment>—</comment>', '<error>missing</error>']
                : [$entry['key'], $this->renderValue($entry['value']), $entry['schema']];
        }

        if ($rows === []) {
            $output->writeln($filter === ''
                ? '<comment>No configuration values found.</comment>'
                : sprintf('<comment>No configuration keys match "%s".</comment>', $filter));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value', 'Schema']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>%d configuration value(s).</info>', count($rows)));

        return Command::SUCCESS;
    }

    /**
     * The merged configuration as data, which is what both outputs render.
     *
     * `value` is the value itself rather than the string the table prints: a
     * document exists so a consumer gets `true` and `3306` back as a boolean
     * and an int, and diffing two environments on stringified values would turn
     * every type difference into a text difference.
     *
     * @return list<array{key: string, value: mixed, schema: string}>
     */
    private function collect(string $filter): array
    {
        $config = Config::getInstance();
        $values = $config->getAllValues();
        $schema = $config->configSchema();
        ksort($values);

        $entries = [];

        /** @psalm-suppress MixedAssignment */
        foreach ($values as $key => $value) {
            if ($filter !== '' && !str_contains($key, $filter)) {
                continue;
            }

            $entries[] = [
                'key' => $key,
                'value' => $value,
                'schema' => $schema->declares($key) ? self::SCHEMA_DECLARED : self::SCHEMA_UNDECLARED,
            ];
        }

        // A declared key nothing provides has no value to list, so it would
        // otherwise be the one kind of drift this table cannot show.
        foreach ($schema->declaredKeys() as $key) {
            if (!array_key_exists($key, $values) && ($filter === '' || str_contains($key, $filter))) {
                $entries[] = ['key' => $key, 'value' => null, 'schema' => self::SCHEMA_MISSING];
            }
        }

        return $entries;
    }

    private function renderValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
This command prints the effective configuration after all sources are merged
(config files, environment values, and values set via GacelaConfig).

<info>What it does:</info>
  - Resolves the merged configuration the same way the application sees it
  - Renders every key/value pair as a table
  - Optionally filters keys by a substring

<info>Examples:</info>
  # Show every configuration value
  bin/gacela debug:config

  # Show only keys containing "database"
  bin/gacela debug:config database
HELP;
    }
}
