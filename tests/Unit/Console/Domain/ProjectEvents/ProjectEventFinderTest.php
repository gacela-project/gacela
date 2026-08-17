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

    /** The prefix of every directory this test builds, and of nothing else. */
    private const string TEMPORARY_PREFIX = 'gacela-project-events-';

    private string $temporaryRoot = '';

    protected function setUp(): void
    {
        $this->temporaryRoot = sys_get_temp_dir() . '/' . self::TEMPORARY_PREFIX . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $root = $this->temporaryRoot;

        // Removing a tree, so the root it walks is asserted to be the one this
        // test built and nothing above it, before anything is unlinked.
        self::assertNotSame('', $root);
        self::assertStringStartsWith(sys_get_temp_dir() . '/' . self::TEMPORARY_PREFIX, $root);

        $this->temporaryRoot = '';

        if (!is_dir($root)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($root);
    }

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

    /**
     * The documented limit, pinned so it stays a decision rather than becoming
     * a surprise: an event that neither names the interface nor ends in `Event`
     * is not found. Its parent is -- and registering against the parent is what
     * covers the family anyway.
     */
    public function test_an_event_that_says_so_nowhere_in_its_own_file_is_not_found(): void
    {
        $found = (new ProjectEventFinder($this->fixtureIterator()))->find();

        self::assertNotContains(self::FIXTURE_NAMESPACE . '\\Nested\\QuietOne', $found);
        self::assertContains(ProjectBaseEvent::class, $found);
    }

    /**
     * Never `vendor/`. A dependency's events are not the project's, and loading
     * every candidate class in an installed package to find that out is exactly
     * the cost this finder is shaped to avoid.
     *
     * Built here rather than committed: `vendor/` is in this repository's
     * `.gitignore`, so a fixture directory by that name would not survive a
     * clone.
     */
    public function test_nothing_under_a_vendor_directory_is_scanned(): void
    {
        $this->writeEventFileIn($this->temporaryRoot . '/vendor/acme/events/src');

        $found = (new ProjectEventFinder($this->iteratorFor($this->temporaryRoot)))->find();

        self::assertSame([], $found);
    }

    /**
     * The counterpart, so the test above proves the `vendor/` rule rather than
     * a temporary directory the finder cannot read at all: the same file, one
     * directory to the side, is found.
     */
    public function test_the_same_file_outside_vendor_is_found(): void
    {
        $this->writeEventFileIn($this->temporaryRoot . '/src');

        $found = (new ProjectEventFinder($this->iteratorFor($this->temporaryRoot)))->find();

        self::assertSame([SomethingHappenedEvent::class], $found);
    }

    /**
     * The directory is `vendor`, not any name starting with it. A project with
     * a `Vendored/` or `vendors/` module of its own keeps its events: matching
     * the bare word would take those away, and matching `/vendor` without the
     * trailing separator is the same mistake spelled differently.
     */
    public function test_a_directory_whose_name_merely_starts_with_vendor_is_scanned(): void
    {
        $this->writeEventFileIn($this->temporaryRoot . '/vendored/src');

        $found = (new ProjectEventFinder($this->iteratorFor($this->temporaryRoot)))->find();

        self::assertSame([SomethingHappenedEvent::class], $found);
    }

    public function test_scanning_nothing_finds_nothing(): void
    {
        self::assertSame([], (new ProjectEventFinder(new AppendIterator()))->find());
    }

    /**
     * A file the finder accepts by every rule it has: the right filename, and a
     * namespace declaration that makes the class it derives an event this
     * process can already load -- the one in the fixture directory.
     *
     * It is never included, only read: the finder derives a name from the path
     * and the `namespace` line, and asks `is_a()` about it. So the only thing
     * the two tests below differ in is where the file sits.
     */
    private function writeEventFileIn(string $directory): void
    {
        mkdir($directory, 0o777, true);

        file_put_contents($directory . '/SomethingHappenedEvent.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace GacelaTest\Unit\Console\Domain\ProjectEvents\Fixture;

            // Body deliberately absent: this file is read, never loaded.
            PHP);
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
