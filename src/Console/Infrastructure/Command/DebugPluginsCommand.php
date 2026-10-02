<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\DebugPlugins\MembershipReport;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Container\Container;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_values;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Who joins each plugin stack and tag, and who listens by `#[AsListener]`,
 * with where each was declared. A member joining by attribute is named
 * nowhere in `gacela.php`, so this is where to see it.
 */
final class DebugPluginsCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('debug:plugins')
            ->setDescription('List plugin stack members, tags and #[AsListener] methods, with where each is declared')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $setup = Config::getInstance()->getSetupGacela();
        $report = MembershipReport::of($setup->getPluginStacks(), $setup->getTags(), Container::attributeMembers());

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode($report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($report['stacks'] === [] && $report['tags'] === [] && $report['listeners'] === [] && $report['problems'] === []) {
            $output->writeln('<comment>No plugin stacks, tags or #[AsListener] methods.</comment>');

            return self::SUCCESS;
        }

        $this->section($output, 'Plugin stacks', ['contract', 'member', 'source'], $report['stacks']);
        $this->section($output, 'Tags', ['tag', 'id', 'source'], $report['tags']);
        $this->section($output, 'Listeners by #[AsListener]', ['event', 'listener'], $report['listeners']);

        foreach ($report['problems'] as $problem) {
            $output->writeln('<error>' . $problem . '</error>');
        }

        return self::SUCCESS;
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, string>> $rows
     */
    private function section(OutputInterface $output, string $title, array $headers, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $output->writeln('<info>' . $title . '</info>');
        $table = new Table($output);
        $table->setHeaders($headers);
        foreach ($rows as $row) {
            $table->addRow(array_values($row));
        }

        $table->render();
        $output->writeln('');
    }
}
