<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp;

use Gacela\Console\Infrastructure\Command\ListModulesCommand;
use Gacela\Console\Testing\ModuleAssertions;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Testing\GacelaTestCase;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerDirectory;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerProfile;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerRepository;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\ReportingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\FixedClock;
use GacelaTest\Feature\ReferenceApp\Support\ReferenceApp;
use GacelaTest\Feature\ReferenceApp\Support\SilentNotificationFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function array_map;

/**
 * The Billing module on its own, with the two modules it depends on replaced.
 *
 * This is the test a team writes every day and the reason `bootstrapModule()`
 * exists: one call instead of a bootstrap, three override APIs and the question
 * of which one applies to which kind of dependency. The two neighbours are
 * replaced two different ways on purpose -- Notification through its Facade,
 * which the module leaves open for exactly this, and Customer through its
 * Factory, because `CustomerFacade` is `final` and nothing can stand in for it.
 *
 * What makes it a *slice* rather than a bootstrap with doubles is the last two
 * tests: the replaced modules are never built, and module discovery sees one
 * module.
 */
final class InvoicingSliceTest extends GacelaTestCase
{
    // Not on GacelaTestCase: the boundary assertions read a graph built by
    // scanning source files, which is `Gacela\Console`, and `Gacela\Framework`
    // does not depend on it. A project writes this one line and has both.
    use ModuleAssertions;

    private string $cacheDir = '';

    protected function setUp(): void
    {
        // Owned by this test, so a warmed cache cannot be confused with one
        // some other application on this machine left in the system temp dir.
        $this->cacheDir = ReferenceApp::createTempDirectory('slice-cache');
        putenv('GACELA_CACHE_DIR=' . $this->cacheDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        ReferenceApp::reset();
        ReferenceApp::removeTempDirectory($this->cacheDir);
    }

    /**
     * The whole flow the module is for, with nothing behind it: the same
     * invoice number, tax and total the end-to-end test produces.
     */
    public function test_billing_issues_an_invoice_with_both_neighbours_replaced(): void
    {
        $notifications = new SilentNotificationFacade();
        $this->bootstrapBillingSlice($notifications);

        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        self::assertSame('ACME-INV-01001', $invoice->getNumber());
        self::assertSame(2_100, $invoice->getTaxCents());
        self::assertSame(12_100, $invoice->getGrossCents());
        self::assertSame(ReferenceApp::FIXED_TODAY, $invoice->getIssuedOn());

        // The double was reached, rather than the module merely not complaining.
        self::assertSame(['ACME-INV-01001'], $notifications->suppressed());
    }

    /**
     * The acceptance criterion. Asserted from the resolver's own events rather
     * than from the setup: a slice that quietly built the modules it replaced
     * would pass every assertion above and still be no slice at all.
     */
    public function test_the_replaced_modules_never_have_their_pillars_built(): void
    {
        $this->bootstrapBillingSlice(new SilentNotificationFacade());

        (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        $built = $this->modulesWhoseClassesWereBuilt();

        self::assertContains('Billing', $built, 'the module under test was not built either');
        self::assertNotContains('Customer', $built);
        self::assertNotContains('Notification', $built);
    }

    /**
     * A module never mentioned is never reached, which is what makes the
     * remaining three modules irrelevant to this test rather than merely
     * unasserted.
     */
    public function test_the_modules_outside_the_slice_are_never_reached(): void
    {
        $this->bootstrapBillingSlice(new SilentNotificationFacade());

        (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        $built = $this->modulesWhoseClassesWereBuilt();

        self::assertNotContains('Payment', $built);
        self::assertNotContains('Reporting', $built);
    }

    /**
     * The slice as the tooling sees it: `list:modules` reports the module under
     * test and nothing else, so `doctor` and the boundary assertions answer
     * about one module too.
     */
    public function test_module_discovery_reports_only_the_module_under_test(): void
    {
        $this->bootstrapBillingSlice(new SilentNotificationFacade());

        $tester = new CommandTester(new ListModulesCommand());
        $tester->execute([]);

        $display = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $display);
        self::assertStringContainsString('ReferenceApp\\Invoicing\\Billing', $display);

        foreach (['Customer', 'Notification', 'Payment', 'Reporting'] as $module) {
            self::assertStringNotContainsString('ReferenceApp\\Invoicing\\' . $module, $display);
        }
    }

    /**
     * The boundaries the application declares, asserted from a test method
     * rather than from a CI job -- against the same `module-rules.json` that
     * {@see InvoicingToolingTest::test_the_module_graph_breaks_none_of_the_declared_rules}
     * runs `debug:graph --check` on, and the analysers read.
     *
     * The whole application, not a slice: a graph narrowed to one module cannot
     * tell a rule about a filtered-out module from a rule about one that no
     * longer exists.
     */
    public function test_the_declared_module_boundaries_hold(): void
    {
        ReferenceApp::bootstrap();

        self::assertNoModuleCycles();
        self::assertModuleRulesHold(ReferenceApp::rulesFile());
    }

    /**
     * Billing's own boundary, written where Billing's tests are. Reporting
     * reads the ledger, so nothing forbids Billing from reaching it except this
     * -- which is the decision worth stating next to the module.
     */
    public function test_billing_depends_on_customer_and_notification_and_nothing_else(): void
    {
        ReferenceApp::bootstrap();

        self::assertModuleDependsOnlyOn(BillingFacade::class, [
            CustomerFacade::class,
            NotificationFacade::class,
        ]);

        self::assertModuleDependsOnlyOn(ReportingFacade::class, [
            BillingFacade::class,
            CustomerFacade::class,
        ]);
    }

    /**
     * Notification through its Facade, Customer through its Factory, and the
     * clock the application asks its host for.
     */
    private function bootstrapBillingSlice(NotificationFacade $notifications): void
    {
        $this->bootstrapModule(
            ReferenceApp::root(),
            BillingFacade::class,
            doubles: [
                NotificationFacade::class => $notifications,
                CustomerFacade::class => $this->customerFactoryDouble(),
            ],
            configFn: static function (GacelaConfig $config): void {
                $config->addExternalService('clock', new FixedClock(ReferenceApp::FIXED_TODAY));
            },
        );
    }

    /**
     * `CustomerFacade` is `final`, so no object can stand in for it and the
     * seam is the Factory every Facade resolves. A standalone `AbstractFactory`
     * carrying the one accessor Billing's calls reach, over a store this test
     * seeded -- no `CustomerConfig`, no `CustomerProvider`, no repository the
     * module would have registered.
     */
    private function customerFactoryDouble(): AbstractFactory
    {
        $repository = new CustomerRepository();
        $repository->save(CustomerProfile::fromArray([
            'reference' => 'acme-nl',
            'name' => 'Acme BV',
            'countryCode' => 'NL',
            'tier' => 'standard',
        ]));

        return new class($repository) extends AbstractFactory {
            public function __construct(
                private readonly CustomerRepository $repository,
            ) {
            }

            public function createCustomerDirectory(): CustomerDirectory
            {
                return new CustomerDirectory($this->repository, 'standard');
            }
        };
    }

    /**
     * The names of the modules whose pillars the resolver built since the
     * bootstrap.
     *
     * @return list<string>
     */
    private function modulesWhoseClassesWereBuilt(): array
    {
        return array_map(
            static fn (ResolvedClassCreatedEvent $event): string => $event->classInfo()->getModuleName(),
            $this->recordedGacelaEventsOf(ResolvedClassCreatedEvent::class),
        );
    }
}
