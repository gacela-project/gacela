<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\IdeMeta;

use Gacela\Console\Application\IdeMeta\IdeMetadataScanner;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Console\Domain\IdeMeta\ProvidedDependencyMap;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\AgreeingBillingProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\BaseRelativeProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\BillingProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\BillingService;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\ChildRelativeProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\ClockInterface;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\ConflictingBillingProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\RelativeReturnTypeProvider;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\ReportService;
use GacelaTest\Unit\Console\Application\IdeMeta\Fixtures\UnwritableTypesProvider;
use PHPUnit\Framework\TestCase;

final class IdeMetadataScannerTest extends TestCase
{
    public function test_a_string_id_is_typed_by_the_return_type_that_registers_it(): void
    {
        $map = $this->scan(BillingProvider::class);

        self::assertSame(BillingService::class, $map->entries()[BillingProvider::BILLING]);
        self::assertSame([], $map->ambiguous());
    }

    public function test_an_id_may_be_typed_by_an_interface(): void
    {
        self::assertSame(ClockInterface::class, $this->scan(BillingProvider::class)->entries()['CLOCK']);
    }

    /**
     * The wildcard the renderer always writes answers these, with the class the
     * id names. Writing the return type over it would swap the declared
     * contract for whatever implements it today.
     */
    public function test_an_id_naming_a_class_or_an_interface_is_left_to_the_wildcard(): void
    {
        $entries = $this->scan(BillingProvider::class)->entries();

        self::assertArrayNotHasKey(ReportService::class, $entries);
        self::assertArrayNotHasKey(ClockInterface::class, $entries);
    }

    public function test_a_method_without_the_attribute_provides_nothing(): void
    {
        self::assertArrayNotHasKey('notProvided', $this->scan(BillingProvider::class)->entries());
    }

    /**
     * A map value is a class name, so there is nothing truthful to write for
     * any of these -- and `BillingService` for a `?BillingService` would hide
     * exactly the null the caller has to handle.
     */
    public function test_a_type_the_map_cannot_express_is_skipped(): void
    {
        $map = $this->scan(UnwritableTypesProvider::class);

        self::assertSame([], $map->entries());
        self::assertSame([], $map->ambiguous());
    }

    /**
     * PHP 8.5 resolves a relative return type inside `getName()`; earlier
     * versions return the literal `self`. Both must produce this same entry, or
     * the generated file differs by the PHP that wrote it and `doctor` reports
     * stale whenever a colleague on another version last ran the command.
     */
    public function test_a_relative_return_type_resolves_the_same_on_every_php_version(): void
    {
        $map = $this->scan(RelativeReturnTypeProvider::class);

        self::assertSame(RelativeReturnTypeProvider::class, $map->entries()['ITSELF']);
    }

    /**
     * `static` is the relative type 8.5 leaves alone, so both versions reach
     * the resolution here rather than only the older ones.
     */
    public function test_a_late_bound_return_type_resolves_to_the_declaring_class(): void
    {
        $map = $this->scan(RelativeReturnTypeProvider::class);

        self::assertSame(RelativeReturnTypeProvider::class, $map->entries()['LATE_BOUND']);
    }

    public function test_a_parent_return_type_resolves_to_the_parent(): void
    {
        $map = $this->scan(ChildRelativeProvider::class);

        self::assertSame(BaseRelativeProvider::class, $map->entries()['FROM_PARENT']);
    }

    public function test_two_providers_registering_one_id_with_one_type_is_not_a_conflict(): void
    {
        $map = $this->scan(BillingProvider::class, AgreeingBillingProvider::class);

        self::assertSame(BillingService::class, $map->entries()[BillingProvider::BILLING]);
        self::assertSame([], $map->ambiguous());
    }

    public function test_two_providers_disagreeing_on_an_id_leave_it_untyped_and_reported(): void
    {
        $map = $this->scan(BillingProvider::class, ConflictingBillingProvider::class);

        self::assertArrayNotHasKey(BillingProvider::BILLING, $map->entries());
        self::assertSame(
            [BillingService::class, ReportService::class],
            $map->ambiguous()[BillingProvider::BILLING],
        );
    }

    /**
     * Discovery order is a directory listing, so the reported classes are
     * sorted: without that the same application produces two different reports.
     */
    public function test_the_reported_classes_do_not_depend_on_discovery_order(): void
    {
        $oneWay = $this->scan(BillingProvider::class, ConflictingBillingProvider::class);
        $other = $this->scan(ConflictingBillingProvider::class, BillingProvider::class);

        self::assertSame($oneWay->ambiguous(), $other->ambiguous());
    }

    public function test_a_module_without_a_provider_contributes_nothing(): void
    {
        $module = new AppModule('App\Empty', 'Empty', BillingService::class);

        self::assertSame([], (new IdeMetadataScanner())->scan([$module])->entries());
    }

    /**
     * Most applications have modules with no Provider. If one of them ended the
     * scan rather than being skipped, the generated map would silently cover
     * only the modules discovered before the first such module.
     */
    public function test_a_module_without_a_provider_does_not_end_the_scan(): void
    {
        $withoutProvider = new AppModule('App\Empty', 'Empty', BillingService::class);
        $withProvider = new AppModule('App\Billing', 'Billing', BillingService::class, null, null, BillingProvider::class);

        $entries = (new IdeMetadataScanner())->scan([$withoutProvider, $withProvider])->entries();

        self::assertSame(BillingService::class, $entries[BillingProvider::BILLING]);
    }

    /**
     * @param class-string ...$providerClasses
     */
    private function scan(string ...$providerClasses): ProvidedDependencyMap
    {
        $modules = [];

        foreach ($providerClasses as $index => $providerClass) {
            $modules[] = new AppModule(
                'App\Module' . $index,
                'Module' . $index,
                BillingService::class,
                null,
                null,
                $providerClass,
            );
        }

        return (new IdeMetadataScanner())->scan($modules);
    }
}
