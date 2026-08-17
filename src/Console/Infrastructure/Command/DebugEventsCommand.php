<?php

declare(strict_types=1);

namespace Gacela\Console\Infrastructure\Command;

use Gacela\Console\Application\Debug\EventCatalog;
use Gacela\Console\Application\Debug\EventInspection;
use Gacela\Console\Application\Debug\EventSource;
use Gacela\Console\ConsoleFacade;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Event\Dispatcher\EventDispatcherInterface;
use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function array_map;
use function count;
use function json_encode;
use function max;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;
use function strrpos;
use function substr;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Every event Gacela can dispatch, which of them anything listens to, and which
 * sit on the hot path -- and the same for the events the application declares
 * itself.
 *
 * Until now the only way to know a listener is registered was that it fired,
 * and the only way to know one is dead was `doctor` -- which reports a target
 * that can never match, not a target that matches nothing this deployment
 * raises. Neither says the other half: which of the events a project could
 * observe nothing is watching.
 *
 * It reads as the counterpart to the matching rule. A listener registered
 * against `AbstractGacelaClassResolverEvent` covers four events, and this is
 * where you see the four.
 *
 * A project's own events are listed under their own heading, marked `project`.
 * For those the question is often the other one -- not "what listens to this"
 * but "does Gacela see my event at all" -- and a class missing from this table
 * is one no `registerSpecificListener()` can be checked against.
 *
 * @method ConsoleFacade getFacade()
 */
#[ServiceMap(method: 'getFacade', className: ConsoleFacade::class)]
final class DebugEventsCommand extends Command
{
    use ServiceResolverAwareTrait;

    protected function configure(): void
    {
        $this->setName('debug:events')
            ->setDescription('List every Gacela and project event, which have listeners, and which are on the hot path')
            ->setHelp($this->getHelpText())
            ->addArgument('filter', InputArgument::OPTIONAL, 'Only events whose class name contains this text', '')
            ->addOption('listened', 'l', InputOption::VALUE_NONE, 'Only events something listens to')
            ->addOption('json', 'j', InputOption::VALUE_NONE, 'Report as a JSON document instead of text');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filter = ConsoleInput::argument($input, 'filter');
        $listenedOnly = $input->getOption('listened') === true;
        $asJson = ConsoleInput::format($input) === 'json';

        $catalog = new EventCatalog();
        $inspections = $catalog->inspect(
            $catalog->eventClasses(),
            $this->specificListenerCounts(),
            $this->genericListenerCount(),
            $this->getFacade()->findProjectEventClasses(),
        );

        $shown = $this->applyFilters($inspections, $filter, $listenedOnly);

        if ($asJson) {
            $output->writeln($this->encode($this->asDocument($inspections, $shown)));

            return Command::SUCCESS;
        }

        $this->writeText($output, $inspections, $shown, $filter, $listenedOnly);

        return Command::SUCCESS;
    }

    /**
     * @param list<EventInspection> $inspections
     *
     * @return list<EventInspection>
     */
    private function applyFilters(array $inspections, string $filter, bool $listenedOnly): array
    {
        $shown = [];

        foreach ($inspections as $inspection) {
            if ($filter !== '' && !str_contains($inspection->className, $filter)) {
                continue;
            }

            if ($listenedOnly && !$inspection->isWatched()) {
                continue;
            }

            $shown[] = $inspection;
        }

        return $shown;
    }

    /**
     * The summary counts the whole catalog, never the filtered view: "3 of 28
     * events have listeners" is the fact somebody ran this for, and it does not
     * change because the argument narrowed the table.
     *
     * `status` carries the verdict on every run the way `debug:modules --json`
     * does, and for the same reason the exit code does not follow it: this
     * command reports, and a command that reports must not start failing builds
     * because somebody added `--json` to it.
     *
     * @param list<EventInspection> $all
     * @param list<EventInspection> $shown
     *
     * @return array<string, mixed>
     */
    private function asDocument(array $all, array $shown): array
    {
        $listeners = $this->specificListenerTotal() + $this->genericListenerCount();
        $listenersEnabled = $this->listenersEnabled();

        return [
            // Everything registered is inert while the switch is off, which is
            // the one state here worth a verdict rather than a number.
            'status' => $listenersEnabled ? 'ok' : 'error',
            'summary' => [
                'events' => count($all),
                'projectEvents' => count($this->projectOf($all)),
                'withListeners' => count($this->listenedOf($all)),
                'hotPath' => count($this->hotPathOf($all)),
                'listeners' => $listeners,
                'genericListeners' => $this->genericListenerCount(),
                'listenersEnabled' => $listenersEnabled,
                'customDispatcher' => $this->customDispatcherClass(),
            ],
            'events' => array_map(
                static fn (EventInspection $inspection): array => [
                    'class' => $inspection->className,
                    'group' => $inspection->group,
                    'source' => $inspection->source->value,
                    'abstract' => $inspection->isAbstract,
                    'hotPath' => $inspection->isHotPath,
                    'listeners' => $inspection->listenerCount(),
                    'targets' => array_keys($inspection->matchedTargets),
                ],
                $shown,
            ),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function encode(array $document): string
    {
        return json_encode($document, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param list<EventInspection> $all
     * @param list<EventInspection> $shown
     */
    private function writeText(
        OutputInterface $output,
        array $all,
        array $shown,
        string $filter,
        bool $listenedOnly,
    ): void {
        ConsoleSection::title($output, 'Gacela events');

        if ($shown === []) {
            $output->writeln($this->nothingToShow($filter, $listenedOnly));
            $output->writeln('');

            return;
        }

        $width = $this->widestShortName($shown);
        $group = null;

        foreach ($shown as $inspection) {
            if ($inspection->group !== $group) {
                $group = $inspection->group;
                $output->writeln(sprintf('  <fg=green>%s</>', $group === '' ? 'Event' : $group));
            }

            $output->writeln('    ' . $this->formatEvent($inspection, $width));
        }

        $output->writeln('');
        $this->writeSummary($output, $all);
    }

    private function formatEvent(EventInspection $inspection, int $width): string
    {
        $marker = $inspection->isWatched() ? '<fg=green>●</>' : '<fg=default>○</>';
        $name = $inspection->shortName();
        $padding = str_repeat(' ', $width - strlen($name));

        // Trimmed: the source column is padded so the listener note lines up,
        // and a row with neither would otherwise carry the padding to the end
        // of the line, which every diff of captured output then argues about.
        return rtrim(sprintf(
            '%s %s%s %s%s',
            $marker,
            $name,
            $padding,
            $this->sourceColumn($inspection),
            $this->listenerNote($inspection),
        ));
    }

    /**
     * One column carrying both facts, because they cannot both be true: the hot
     * path is the framework's list of events dispatched on every warm resolve,
     * and a project event is never on it.
     *
     * A project event is marked rather than left blank. The group heading above
     * it is a namespace, which a reader may or may not recognise as their own,
     * and the fact worth stating in a report of "every event there is" is which
     * of them the application declared.
     */
    private function sourceColumn(EventInspection $inspection): string
    {
        if ($inspection->isHotPath) {
            return '<fg=yellow>hot path</>';
        }

        if ($inspection->source === EventSource::Project) {
            return '<fg=magenta>project </>';
        }

        return '        ';
    }

    /**
     * The targets are the point rather than the count: an event covered by a
     * parent-class listener reads as "1 listener" and leaves you looking for a
     * registration naming this class, which is not there.
     */
    private function listenerNote(EventInspection $inspection): string
    {
        if ($inspection->isAbstract) {
            return '  <fg=cyan>(a listener target, never dispatched)</>';
        }

        if (!$inspection->isWatched()) {
            return '';
        }

        $count = $inspection->listenerCount();
        $note = sprintf('  %d listener%s', $count, $count === 1 ? '' : 's');

        foreach (array_keys($inspection->matchedTargets) as $target) {
            $note .= sprintf(' <fg=cyan>via %s</>', $this->shortNameOf($target));
        }

        if ($inspection->genericListenerCount > 0) {
            $note .= ' <fg=cyan>via registerGenericListener()</>';
        }

        return $note;
    }

    /**
     * @param list<EventInspection> $all
     */
    private function writeSummary(OutputInterface $output, array $all): void
    {
        $listened = count($this->listenedOf($all));
        $project = count($this->projectOf($all));

        $output->writeln(sprintf(
            '<fg=cyan>Summary:</> %d events, %d with listeners, %d on the hot path',
            count($all),
            $listened,
            count($this->hotPathOf($all)),
        ));

        // Only when there are any: an application that dispatches none of its
        // own should not be told about a number that is always zero, and a
        // project that expected some and reads "0 declared by this project" is
        // being told the scan found nothing -- which is the answer it needs.
        if ($project > 0) {
            $output->writeln(sprintf(
                '<fg=cyan>Of these:</> %d declared by this project',
                $project,
            ));
        }

        if (!$this->listenersEnabled()) {
            $output->writeln('<comment>disableEventListeners() is in effect, so none of these listeners runs.</comment>');
        }

        $customDispatcher = $this->customDispatcherClass();

        if ($customDispatcher !== null) {
            // Without this the table is half the story: the listeners above are
            // what the *configuration* registered, and a supplied dispatcher
            // carries every event on to whatever the application does with it,
            // which this command cannot see into.
            $output->writeln(sprintf(
                '<comment>setEventDispatcher(%s) is in effect, so every event above also reaches it.</comment>',
                $this->shortNameOf($customDispatcher),
            ));
        }

        $output->writeln('');
    }

    private function nothingToShow(string $filter, bool $listenedOnly): string
    {
        if ($listenedOnly) {
            return '  <comment>Nothing listens to any Gacela event.</comment>';
        }

        return sprintf('  <comment>No Gacela event contains "%s".</comment>', $filter);
    }

    /**
     * @param list<EventInspection> $inspections
     *
     * @return list<EventInspection>
     */
    private function listenedOf(array $inspections): array
    {
        $listened = [];

        foreach ($inspections as $inspection) {
            if ($inspection->isWatched()) {
                $listened[] = $inspection;
            }
        }

        return $listened;
    }

    /**
     * @param list<EventInspection> $inspections
     *
     * @return list<EventInspection>
     */
    private function projectOf(array $inspections): array
    {
        $project = [];

        foreach ($inspections as $inspection) {
            if ($inspection->source === EventSource::Project) {
                $project[] = $inspection;
            }
        }

        return $project;
    }

    /**
     * @param list<EventInspection> $inspections
     *
     * @return list<EventInspection>
     */
    private function hotPathOf(array $inspections): array
    {
        $hot = [];

        foreach ($inspections as $inspection) {
            if ($inspection->isHotPath) {
                $hot[] = $inspection;
            }
        }

        return $hot;
    }

    /**
     * @param list<EventInspection> $inspections
     */
    private function widestShortName(array $inspections): int
    {
        $width = 0;

        foreach ($inspections as $inspection) {
            $width = max($width, strlen($inspection->shortName()));
        }

        return $width;
    }

    private function shortNameOf(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }

    /**
     * Read off the concrete setup, the way `doctor` reads the same map: the
     * listener registry is not part of `SetupGacelaInterface`, and widening a
     * public contract to reach a diagnostic would be the tail wagging the dog.
     *
     * @return array<class-string, int>
     */
    private function specificListenerCounts(): array
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return [];
        }

        return array_map(
            count(...),
            $setup->getSpecificListeners() ?? [],
        );
    }

    private function specificListenerTotal(): int
    {
        $total = 0;

        foreach ($this->specificListenerCounts() as $count) {
            $total += $count;
        }

        return $total;
    }

    private function genericListenerCount(): int
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return 0;
        }

        return count($setup->getGenericListeners() ?? []);
    }

    /**
     * The dispatcher the application handed over, if it did.
     *
     * Reported because the listener table is only half of what happens to an
     * event once one is installed: the configured listeners run, and then the
     * event goes on to a bus this command knows nothing about.
     *
     * @return class-string|null
     */
    private function customDispatcherClass(): ?string
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return null;
        }

        $supplied = $setup->getSuppliedEventDispatcher();

        if (!$supplied instanceof EventDispatcherInterface) {
            return null;
        }

        return $supplied::class;
    }

    /**
     * Whether a registered listener would run.
     *
     * `canCreateEventDispatcher()` answers false for two different situations --
     * `disableEventListeners()` was called, and nothing was registered at all --
     * and only the first is a project being told something. So a project that
     * registered nothing is reported as enabled: there is no listener for the
     * switch to be suppressing, and "listeners are disabled" beside an empty
     * table would send somebody hunting for a call that is not there.
     */
    private function listenersEnabled(): bool
    {
        $setup = Config::getInstance()->getSetupGacela();

        if (!$setup instanceof SetupGacela) {
            return true;
        }

        if ($this->specificListenerTotal() + $this->genericListenerCount() === 0) {
            return true;
        }

        return $setup->canCreateEventDispatcher();
    }

    private function getHelpText(): string
    {
        return <<<'HELP'
Lists every event class the framework can dispatch, whether anything listens to
it, and whether it is dispatched on the class-resolution hot path.

The events this project declares are listed too, marked `project`. They are
found under the paths module discovery walks -- `appModulePaths`, or the
application root -- by implementing GacelaEventInterface or being named
`*Event`. An event of yours that is missing is one no listener registration can
be checked against.

A specific listener matches by inheritance, so an event can be covered by a
listener that never names it -- one registered against a parent class or against
GacelaEventInterface. The listener column names the target that covers it.

<info>Examples:</info>
  # The whole catalog, the framework's events and yours
  bin/gacela debug:events

  # Only the events something listens to
  bin/gacela debug:events --listened

  # Only the class-resolution events
  bin/gacela debug:events ClassResolver

  # Only your own, if they share a namespace
  bin/gacela debug:events App\\

<comment>Complements:</comment>
  bin/gacela doctor                names a listener target no event can ever be
HELP;
    }
}
