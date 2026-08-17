<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis;

use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\PublicApiSurface;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\BillingValueObject;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\PublishedBehaviour;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\PublishedContract;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\PublishedInvoice;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\PublishedStatus;
use GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing\UnpublishedInvoiceDraft;
use PHPUnit\Framework\TestCase;

/**
 * What a module exports, which is the one question the boundary could not
 * answer: everything else about it is namespace arithmetic, and this is the
 * module's own declaration.
 */
final class ModuleBoundaryTest extends TestCase
{
    private const ROOT = 'App\Modules';

    /** Real classes, because the attribute half is read by reflection. */
    private const FIXTURE_ROOT = 'GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule';

    public function test_a_class_carrying_the_attribute_is_public(): void
    {
        self::assertTrue($this->fixtureBoundary()->isPublicApi(PublishedInvoice::class));
    }

    public function test_an_enum_carrying_the_attribute_is_public(): void
    {
        self::assertTrue($this->fixtureBoundary()->isPublicApi(PublishedStatus::class));
    }

    /**
     * An interface is not a class to `class_exists()`, so the surface asks twice
     * -- and an interface is exactly the kind of thing a module publishes.
     */
    public function test_an_interface_carrying_the_attribute_is_public(): void
    {
        self::assertTrue($this->fixtureBoundary()->isPublicApi(PublishedContract::class));
    }

    /**
     * `TARGET_CLASS` permits a trait and nothing reads it there. Nothing can
     * name a trait across a boundary -- it is `use`d into a class rather than
     * instantiated, named statically or called on -- so publishing one would be
     * a promise no rule keeps. A decision, pinned, rather than an oversight.
     */
    public function test_a_trait_carrying_the_attribute_is_not_public(): void
    {
        self::assertFalse($this->fixtureBoundary()->isPublicApi(PublishedBehaviour::class));
    }

    public function test_a_class_without_the_attribute_is_not_public(): void
    {
        self::assertFalse($this->fixtureBoundary()->isPublicApi(BillingValueObject::class));
    }

    /**
     * Exporting a base class must not export everything anyone ever extends
     * from it: what a module publishes is a decision per class.
     */
    public function test_the_attribute_is_not_inherited_by_a_subclass(): void
    {
        self::assertFalse($this->fixtureBoundary()->isPublicApi(UnpublishedInvoiceDraft::class));
    }

    /**
     * The attribute answers on its own, with no convention configured -- it is
     * the escape hatch for the class that lives where it already lives.
     */
    public function test_the_attribute_is_read_with_no_segments_configured(): void
    {
        self::assertTrue($this->fixtureBoundary([])->isPublicApi(PublishedInvoice::class));
    }

    /**
     * A name nothing can load is not a crash: the analysers treat "cannot tell"
     * as "no finding", and a rule that died on an unresolvable receiver would
     * fail the run instead of reporting a boundary.
     */
    public function test_a_class_that_cannot_be_loaded_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Billing\Domain\NoSuchClass'));
    }

    public function test_a_class_under_a_public_segment_is_public(): void
    {
        self::assertTrue($this->boundary()->isPublicApi('App\Modules\Billing\Shared\Invoice'));
    }

    /**
     * The segment does not have to sit directly under the module: a module that
     * groups its shapes deeper still publishes them.
     */
    public function test_a_public_segment_is_matched_at_any_depth(): void
    {
        self::assertTrue($this->boundary()->isPublicApi('App\Modules\Billing\Domain\Dto\Money'));
    }

    /**
     * Whole segments, never prefixes. On a prefix match `Event` would publish
     * every module's `EventHandler\` internals on the strength of a naming
     * coincidence.
     */
    public function test_a_segment_that_merely_starts_with_a_public_one_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Billing\EventHandler\Foo'));
    }

    public function test_a_segment_that_merely_ends_with_a_public_one_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Billing\DomainEvent\Foo'));
    }

    /**
     * The class's own short name is not a namespace segment: a class *called*
     * `Event` is not a namespace of published shapes.
     */
    public function test_the_class_short_name_is_not_a_segment(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Billing\Event'));
    }

    public function test_a_class_sitting_directly_in_its_module_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Billing\BillingFacade'));
    }

    /**
     * Every configured segment is considered, or only the first would ever
     * publish anything.
     */
    public function test_every_configured_segment_is_considered(): void
    {
        $boundary = $this->boundary(['Shared', 'Transfer']);

        self::assertTrue($boundary->isPublicApi('App\Modules\Billing\Transfer\Money'));
    }

    /**
     * An explicitly empty list turns the convention off, leaving the attribute
     * as the only way to publish.
     */
    public function test_no_segments_configured_publishes_nothing_by_convention(): void
    {
        self::assertFalse($this->boundary([])->isPublicApi('App\Modules\Billing\Shared\Invoice'));
    }

    /**
     * There is no module for it to be the surface of. A class directly under the
     * root has no segment naming a module, so nothing owns it and nothing can
     * export it.
     */
    public function test_a_class_in_no_module_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('App\Modules\Loose'));
    }

    public function test_a_class_outside_the_root_namespace_is_not_public(): void
    {
        self::assertFalse($this->boundary()->isPublicApi('Vendor\Library\Shared\Thing'));
    }

    /**
     * `sharedNamespaces` and the public segments are different questions. The
     * shared kernel belongs to no module; a module's `Shared\` sub-namespace is
     * that module's, and stays that module's after it is published.
     */
    public function test_a_published_class_still_belongs_to_its_module(): void
    {
        $boundary = $this->boundary();

        self::assertSame('App\Modules\Billing', $boundary->moduleOf('App\Modules\Billing\Shared\Invoice'));
        self::assertFalse($boundary->isShared('App\Modules\Billing\Shared\Invoice'));
    }

    /**
     * @param list<string> $publicApiSegments
     */
    private function boundary(array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS): ModuleBoundary
    {
        return new ModuleBoundary(self::ROOT, 1, [], $publicApiSegments);
    }

    /**
     * @param list<string> $publicApiSegments
     */
    private function fixtureBoundary(
        array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
    ): ModuleBoundary {
        return new ModuleBoundary(self::FIXTURE_ROOT, 1, [], $publicApiSegments);
    }
}
