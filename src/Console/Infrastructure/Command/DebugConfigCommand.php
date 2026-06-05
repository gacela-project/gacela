<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Config\Config;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function count;
use function is_bool;
use function is_scalar;
use function ksort;
use function sprintf;

use function str_contains;

use const JSON_UNESCAPED_SLASHES;

/**
 * @method ConsoleFacade getFacade()
 */
final class DebugConfigCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('debug:config')
            ->setDescription('Show the effective merged configuration')
            ->addArgument('filter', InputArgument::OPTIONAL, 'Only show keys containing this substring')
            ->setHelp($this->getHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = (string)$input->getArgument('filter');

        $values = Config::getInstance()->getAllValues();
        ksort($values);

        $rows = [];
        /** @psalm-suppress MixedAssignment */
        foreach ($values as $key => $value) {
            if ($filter !== '' && !str_contains($key, $filter)) {
                continue;
            }

            $rows[] = [$key, $this->renderValue($value)];
        }

        if ($rows === []) {
            $output->writeln($filter === ''
                ? '<comment>No configuration values found.</comment>'
                : sprintf('<comment>No configuration keys match "%s".</comment>', $filter));

            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Key', 'Value']);
        $table->setRows($rows);
        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>%d configuration value(s).</info>', count($rows)));

        return Command::SUCCESS;
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

        return (string)json_encode($value, JSON_UNESCAPED_SLASHES);
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
