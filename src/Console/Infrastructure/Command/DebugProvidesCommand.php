<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\ConsoleFacade;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Attribute\ProvidesScanner;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function class_exists;
use function json_encode;
use function ksort;
use function sprintf;
use function str_contains;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Which Provider declares an id, across every module.
 *
 * `getProvidedDependency()` answers `null` for an id nothing provides, and says
 * nothing about it — the null travels until something calls a method on it.
 * The question that follows is "who was supposed to declare this?", and until
 * now the only way to ask it was `debug:module` once per module.
 *
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DebugProvidesCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('debug:provides')
            ->setDescription('Find which Provider declares an id with #[Provides]')
            ->addArgument('id', InputArgument::OPTIONAL, 'Only ids containing this text', '')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Output machine-readable JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $needle = ConsoleInput::argument($input, 'id');
        $declarations = $this->declarations($this->getFacade()->findAllAppModules(''), $needle);

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode(
                $declarations,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ));

            return self::SUCCESS;
        }

        if ($declarations === []) {
            // Reported rather than left blank: "nothing declares this" is the
            // answer somebody chasing a null from getProvidedDependency() came
            // for, and an empty table does not say it.
            $output->writeln($needle === ''
                ? '<comment>No module declares anything with #[Provides].</comment>'
                : sprintf('<comment>No #[Provides] id contains "%s".</comment>', $needle));

            return self::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['id', 'module', 'provider', 'method']);

        foreach ($declarations as $id => $rows) {
            foreach ($rows as $row) {
                $table->addRow([$id, $row['module'], $row['provider'], $row['method'] . '()']);
            }
        }

        $table->render();

        return self::SUCCESS;
    }

    /**
     * @param list<AppModule> $modules
     *
     * @return array<string, list<array{module: string, provider: string, method: string}>>
     */
    private function declarations(array $modules, string $needle): array
    {
        $declarations = [];

        foreach ($modules as $module) {
            $providerClass = $module->providerClass();
            if ($providerClass === null) {
                continue;
            }

            if (!class_exists($providerClass)) {
                continue;
            }

            foreach (ProvidesScanner::entriesFor(new ReflectionClass($providerClass)) as $entry) {
                if ($needle !== '' && !str_contains($entry['id'], $needle)) {
                    continue;
                }

                $declarations[$entry['id']][] = [
                    'module' => $module->moduleName(),
                    'provider' => $providerClass,
                    'method' => $entry['method']->getName(),
                ];
            }
        }

        // Sorted so two runs of one project agree, and so an id declared in
        // several modules shows its rows together.
        ksort($declarations);

        return $declarations;
    }
}
