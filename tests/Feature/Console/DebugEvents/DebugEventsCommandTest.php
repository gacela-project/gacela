<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugEvents;

use Gacela\Console\Application\Debug\EventCatalog;
use Gacela\Console\ConsoleFacade;
use Gacela\Console\Infrastructure\Command\DebugEventsCommand;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCachedEvent;
use Gacela\Framework\Event\Config\ConfigKeyReadEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Event\Provider\ProviderRegisteredEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Console\DebugEvents\Fixture\StockRunLowEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function count;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

final class DebugEventsCommandTest extends TestCase
{
    public function test_it_lists_every_event_grouped_by_namespace(): void
    {
        $this->bootstrapWithoutListeners();

        $tester = $this->runCommand([]);
        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('Gacela events', $display);
        self::assertStringContainsString('ClassResolver\Cache', $display);
        self::assertStringContainsString('ResolvedClassCachedEvent', $display);
        self::assertStringContainsString('ProviderRegisteredEvent', $display);
    }

    public function test_it_marks_the_hot_path_events(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('ConfigKeyReadEvent', $display);
        self::assertStringContainsString('hot path', $display);
        self::assertStringContainsString(sprintf(
            '%d events, 0 with listeners, %d on the hot path',
            $this->catalogSize(),
            count(EventCatalog::hotPathEvents()),
        ), $display);
    }

    public function test_the_abstract_parent_is_named_as_a_target_rather_than_an_event(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('AbstractGacelaClassResolverEvent', $display);
        self::assertStringContainsString('a listener target, never dispatched', $display);
    }

    public function test_a_listener_on_a_parent_class_is_reported_against_every_event_it_covers(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(
                AbstractGacelaClassResolverEvent::class,
                static function (): void {},
            );
        });

        $display = $this->runCommand(['--listened' => true])->getDisplay();

        self::assertStringContainsString('ResolvedClassCachedEvent', $display);
        self::assertStringContainsString('via AbstractGacelaClassResolverEvent', $display);
        self::assertStringNotContainsString('ProviderRegisteredEvent', $display);
        // The four concrete resolver events, and not the abstract parent the
        // listener names: nothing dispatches that one.
        self::assertStringContainsString('4 with listeners', $display);
        self::assertStringNotContainsString('AbstractGacelaClassResolverEvent  ', $display);
    }

    public function test_a_generic_listener_is_reported_against_every_event(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerGenericListener(static function (GacelaEventInterface $event): void {});
        });

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('via registerGenericListener()', $display);
        // Every event but the abstract parent, which is a target and not a
        // dispatch.
        self::assertStringContainsString(sprintf(
            '%d events, %d with listeners',
            $this->catalogSize(),
            $this->catalogSize() - 1,
        ), $display);
    }

    public function test_the_argument_narrows_to_matching_class_names(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand(['filter' => 'ClassNameFinder'])->getDisplay();

        self::assertStringContainsString('ClassNameNotFoundEvent', $display);
        self::assertStringNotContainsString('ProviderRegisteredEvent', $display);
        // The summary counts the catalog, never the filtered view.
        self::assertStringContainsString(sprintf('%d events', $this->catalogSize()), $display);
    }

    public function test_an_argument_matching_nothing_says_so(): void
    {
        $this->bootstrapWithoutListeners();

        $tester = $this->runCommand(['filter' => 'NoSuchEvent']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('No event contains "NoSuchEvent"', $tester->getDisplay());
    }

    public function test_listened_with_nothing_registered_says_so(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand(['--listened' => true])->getDisplay();

        self::assertStringContainsString("Nothing listens to any event, Gacela's or this project's.", $display);
    }

    /**
     * `disableEventListeners()` builds no dispatcher, so a listed listener does
     * not run -- which reading the table alone would not tell you.
     */
    public function test_it_says_when_the_dispatcher_is_disabled(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ProviderRegisteredEvent::class, static function (): void {});
            $config->disableEventListeners();
        });

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('disableEventListeners() is in effect', $display);
    }

    public function test_the_json_document_carries_the_catalog_and_a_summary(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ConfigKeyReadEvent::class, static function (): void {});
        });

        $document = $this->runAsJson([]);

        self::assertSame('ok', $document['status']);
        self::assertSame($this->catalogSize(), $document['summary']['events']);
        self::assertSame(1, $document['summary']['withListeners']);
        self::assertSame(count(EventCatalog::hotPathEvents()), $document['summary']['hotPath']);
        self::assertSame(1, $document['summary']['listeners']);
        self::assertSame(0, $document['summary']['genericListeners']);
        self::assertTrue($document['summary']['listenersEnabled']);
        self::assertCount($this->catalogSize(), $document['events']);
    }

    public function test_each_json_event_carries_its_group_hot_path_and_targets(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(
                AbstractGacelaClassResolverEvent::class,
                static function (): void {},
            );
        });

        $document = $this->runAsJson([]);
        $event = $this->eventNamed($document, ResolvedClassCachedEvent::class);

        self::assertSame('ClassResolver', $event['group']);
        self::assertFalse($event['abstract']);
        self::assertTrue($event['hotPath']);
        self::assertSame(1, $event['listeners']);
        self::assertSame([AbstractGacelaClassResolverEvent::class], $event['targets']);
    }

    /**
     * The filter narrows the listing and not the summary, so a script reading
     * `summary` gets the same numbers whatever the argument was.
     */
    public function test_the_json_summary_ignores_the_filter(): void
    {
        $this->bootstrapWithoutListeners();

        $document = $this->runAsJson(['filter' => 'Bootstrap']);

        self::assertSame($this->catalogSize(), $document['summary']['events']);
        self::assertCount(2, $document['events']);
    }

    /**
     * The one state worth a verdict: everything registered is inert. The exit
     * code stays SUCCESS, because a reporting command must not start failing
     * builds when somebody adds `--json` to it.
     */
    public function test_the_json_status_is_error_when_registered_listeners_cannot_run(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(ProviderRegisteredEvent::class, static function (): void {});
            $config->disableEventListeners();
        });

        $tester = $this->runCommand(['--json' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        /** @var array<string, mixed> $document */
        $document = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('error', $document['status']);
        self::assertFalse($document['summary']['listenersEnabled']);
    }

    /**
     * `canCreateEventDispatcher()` is false here too -- there is nothing to
     * build a dispatcher for -- and reporting that as "listeners are disabled"
     * sends somebody hunting for a `disableEventListeners()` call that is not
     * there.
     */
    public function test_a_project_registering_nothing_is_not_reported_as_disabled(): void
    {
        $this->bootstrapWithoutListeners();

        $document = $this->runAsJson([]);

        self::assertSame('ok', $document['status']);
        self::assertTrue($document['summary']['listenersEnabled']);
        self::assertStringNotContainsString(
            'disableEventListeners()',
            $this->runCommand([])->getDisplay(),
        );
    }

    /**
     * The half of the report that used to be missing: an event the application
     * declared. Listed under its own namespace and marked `project`, so a
     * reader can tell what Gacela found from what Gacela ships -- and, when a
     * class of theirs is absent, that no listener registration against it can
     * be checked.
     */
    public function test_a_project_event_is_listed_and_marked_as_the_projects(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand([])->getDisplay();

        self::assertStringContainsString('StockRunLowEvent', $display);
        self::assertStringContainsString('project', $display);
        // Grouped by its own namespace, written out in full: there is no
        // framework root to make it relative to.
        self::assertStringContainsString('GacelaTest\Feature\Console\DebugEvents\Fixture', $display);
        self::assertStringContainsString('1 declared by this project', $display);
    }

    public function test_the_project_marker_is_absent_when_only_the_frameworks_events_are_shown(): void
    {
        $this->bootstrapWithoutListeners();

        $display = $this->runCommand(['filter' => 'ClassResolver'])->getDisplay();

        self::assertStringNotContainsString('StockRunLowEvent', $display);
        // The summary still counts the whole catalog, so the project line stays.
        self::assertStringContainsString('1 declared by this project', $display);
    }

    /**
     * The doctor's counterpart to this: a listener on a project event is a
     * listener like any other, and the table has to say so or `debug:events`
     * cannot answer "is my listener registered" for the events a project cares
     * about most.
     */
    public function test_a_listener_on_a_project_event_is_reported(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->registerSpecificListener(StockRunLowEvent::class, static function (): void {});
        });

        $display = $this->runCommand(['--listened' => true])->getDisplay();

        self::assertStringContainsString('StockRunLowEvent', $display);
        self::assertStringContainsString('1 listener via StockRunLowEvent', $display);
        self::assertStringContainsString('1 with listeners', $display);
    }

    public function test_the_json_names_the_source_of_every_event(): void
    {
        $this->bootstrapWithoutListeners();

        $document = $this->runAsJson([]);

        self::assertSame('project', $this->eventNamed($document, StockRunLowEvent::class)['source']);
        self::assertSame('framework', $this->eventNamed($document, ResolvedClassCachedEvent::class)['source']);
        self::assertSame(1, $document['summary']['projectEvents']);
    }

    /**
     * Framework first, then the project's -- two runs rather than one
     * interleaved list, so "my events" is a place in the output and not a
     * search.
     */
    public function test_the_frameworks_events_are_listed_before_the_projects(): void
    {
        $this->bootstrapWithoutListeners();

        /** @var list<array<string, mixed>> $events */
        $events = $this->runAsJson([])['events'];
        $sources = [];

        foreach ($events as $event) {
            $sources[] = $event['source'];
        }

        self::assertSame('framework', $sources[0]);
        self::assertSame('project', $sources[count($sources) - 1]);
    }

    /**
     * Everything the command lists: the framework's catalog and the events of
     * the fixture application it is pointed at.
     */
    private function catalogSize(): int
    {
        return count((new EventCatalog())->eventClasses())
            + count((new ConsoleFacade())->findProjectEventClasses());
    }

    private function bootstrapWithoutListeners(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
        });
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return array<string, mixed>
     */
    private function eventNamed(array $document, string $className): array
    {
        /** @var list<array<string, mixed>> $events */
        $events = $document['events'];

        foreach ($events as $event) {
            if ($event['class'] === $className) {
                return $event;
            }
        }

        self::fail($className . ' is missing from the document, which carries ' . count($events) . ' events');
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function runAsJson(array $input): array
    {
        $display = $this->runCommand($input + ['--json' => true])->getDisplay();

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($display, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function runCommand(array $input): CommandTester
    {
        $tester = new CommandTester(new DebugEventsCommand());
        $tester->execute($input);

        return $tester;
    }
}
