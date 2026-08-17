<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Domain\ProjectEvents;

use AppendIterator;
use FilesystemIterator;
use Gacela\Console\Domain\ProjectEvents\ProjectEventFinder;
use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\Nested\NestedProjectEvent;
use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\OrderShipped;
use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\ProjectBaseEvent;
use GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture\SomethingHappenedEvent;
use OuterIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The fixture directory holds one of each shape the finder has to get right,
 * and two it has to leave alone.
 */
final class ProjectEventFinderTest extends TestCase
{
    private const string FIXTURE_NAMESPACE = 'GacelaTest\\Unit\\Console\\Domain\\ProjectEvents\\Fixture';

    public function test_it_finds_the_events_under_the_scanned_path(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator()))->find();

        self::assertContains(SomethingHappenedEvent::class, $found);
        self::assertContains(NestedProjectEvent::class, $found);
        self::assertContains(ProjectBaseEvent::class, $found);
    }

    /**
     * The name is a convention, not the rule: what makes a class an event is
     * the interface, and `App\Billing\Event\InvoiceIssued` is how a project
     * that groups its events in a namespace writes one.
     */
    public function test_an_event_that_does_not_end_in_event_is_found_by_its_interface(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator()))->find();

        self::assertContains(OrderShipped::class, $found);
    }

    /**
     * A class named like an event and implementing nothing is not one, and a
     * file naming the interface for another reason -- a listener type-hinting
     * it -- is not one either. Both are candidates the pre-filter lets through
     * on purpose, and both are rejected by the only question that decides.
     */
    public function test_a_class_that_is_not_an_event_is_left_out(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator()))->find();

        self::assertNotContains(self::FIXTURE_NAMESPACE . '\\NotReallyAnEvent', $found);
        self::assertNotContains(self::FIXTURE_NAMESPACE . '\\RecordingListener', $found);
    }

    public function test_the_classes_come_back_sorted_and_unique(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator()))->find();
        $sorted = $found;
        sort($sorted);

        self::assertSame($sorted, $found);
        self::assertSame($found, array_values(array_unique($found)));
    }

    /**
     * The application said what its namespaces are, so a class outside them is
     * somebody else's -- a fixture, or something vendored into a scanned path.
     */
    public function test_a_declared_project_namespace_excludes_everything_else(): void
    {
        $found = (new ProjectEventFinder(
            $this->fixtureIterator(),
            [self::FIXTURE_NAMESPACE . '\\Nested'],
        ))->find();

        self::assertSame([NestedProjectEvent::class], $found);
    }

    public function test_a_project_namespace_that_matches_nothing_finds_nothing(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator(), ['App\\Somewhere\\Else']))->find();

        self::assertSame([], $found);
    }

    /**
     * A prefix has to end at a namespace separator: `App\Billing` does not own
     * `App\BillingReports`, whatever `str_starts_with` alone would say.
     */
    public function test_a_namespace_prefix_does_not_claim_a_longer_neighbour(): void
    {
        $found = (new ProjectEventFinder(
            $this->fixtureIterator(),
            [self::FIXTURE_NAMESPACE . '\\Nest'],
        ))->find();

        self::assertSame([], $found);
    }

    /**
     * Gacela's own events are catalogued from the installed package. A project
     * whose scan path includes them -- this repository, for one -- must not see
     * them reported twice.
     */
    public function test_the_frameworks_own_events_are_never_reported_as_the_projects(): void
    {
        $found = (new ProjectEventFinder($this->iteratorFor(
            __DIR__ . '/../../../../../src/Framework/Event',
        )))->find();

        self::assertSame([], $found);
    }

    public function test_scanning_nothing_finds_nothing(): void
    {
        self::assertSame([], (new ProjectEventFinder(new AppendIterator()))->find());
    }

    /**
     * @return OuterIterator<array-key, SplFileInfo>
     */
    private function fixtureIterator(): OuterIterator
    {
        return $this->iteratorFor(__DIR__ . '/Fixture');
    }

    /**
     * @return OuterIterator<array-key, SplFileInfo>
     */
    private function iteratorFor(string $directory): OuterIterator
    {
        /** @var OuterIterator<array-key, SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        return $iterator;
    }
}
