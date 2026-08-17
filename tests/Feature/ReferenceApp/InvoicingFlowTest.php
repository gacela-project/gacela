<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\ClassResolver\ResolvedClassCreatedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Infrastructure\ResolverActivityLog;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\PaymentApi;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\ReportingFacade;
use GacelaTest\Feature\ReferenceApp\Support\RecordingEventDispatcher;
use GacelaTest\Feature\ReferenceApp\Support\RecordingPsr14Bus;
use GacelaTest\Feature\ReferenceApp\Support\ReferenceApp;
use GacelaTest\Feature\ReferenceApp\Support\SilentNotificationFacade;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * What the invoicing application does, from the outside.
 *
 * One flow -- register a customer, issue an invoice, take the payment, read the
 * report -- run twice: once as a developer runs it, and once as production in
 * the EU region, where a different tax rate, a second tax rule, a second
 * notification channel and the real gateway are all in force. Nothing below
 * asserts on a container, a resolver or a config source; every assertion is a
 * number or a string the application produced.
 *
 * Every bootstrap here goes through {@see ReferenceApp::bootstrap()}, which
 * passes a closure *and* lets `gacela.php` merge onto it -- the arrangement a
 * host framework uses, and the one that lost listeners before #866.
 */
final class InvoicingFlowTest extends TestCase
{
    private string $cacheDir = '';

    protected function setUp(): void
    {
        // Owned by this test, so a warmed cache cannot be confused with one
        // some other application on this machine left in the system temp dir.
        $this->cacheDir = ReferenceApp::createTempDirectory('flow-cache');
        putenv('GACELA_CACHE_DIR=' . $this->cacheDir);
    }

    protected function tearDown(): void
    {
        ReferenceApp::reset();
        ReferenceApp::removeTempDirectory($this->cacheDir);
    }

    public function test_an_invoice_goes_out_taxed_notified_paid_and_reported(): void
    {
        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        // 21% is what `config/app.php` declares outside production.
        self::assertSame('ACME-INV-01001', $invoice->getNumber());
        self::assertSame(10_000, $invoice->getNetCents());
        self::assertSame(2_100, $invoice->getTaxCents());
        self::assertSame(12_100, $invoice->getGrossCents());
        self::assertSame('EUR', $invoice->getCurrency());
        self::assertSame(ReferenceApp::FIXED_TODAY, $invoice->getIssuedOn());

        // Email only: the webhook is a production channel.
        self::assertSame(
            ['email:Acme BV:Invoice ACME-INV-01001'],
            (new NotificationFacade())->deliveries(),
        );

        $receipt = (new PaymentApi())->pay($invoice->getNumber(), $invoice->getGrossCents(), 'card');

        self::assertSame('card', $receipt->method);
        self::assertSame('sandbox.acme-pay.test', $receipt->endpoint);
        self::assertSame(['ACME-INV-01001:12100:card'], (new PaymentApi())->ledgerEntries());

        $report = (new ReportingFacade())->revenueReport();

        self::assertSame(1, $report->invoiceCount);
        self::assertSame(12_100, $report->grossCents);
        self::assertSame(2_100, $report->taxCents);
        self::assertSame(['Acme BV' => 12_100], $report->grossByCustomerName);
    }

    /**
     * `APP_ENV` selects `gacela-prod.php` and `config/app-prod.php`; the
     * declared `APP_REGION` dimension then selects `config/app-prod-eu.php` on
     * top of both. Four things change at once, and all four are visible from
     * outside the application.
     */
    public function test_production_in_the_eu_region_invoices_differently(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=eu');

        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        // 19% from the EU layer, plus the 3% digital services surcharge that
        // only `gacela-prod.php` adds to the stack.
        self::assertSame(2_200, $invoice->getTaxCents());
        self::assertSame(12_200, $invoice->getGrossCents());

        // The webhook channel is added by the same production config, and both
        // channels see the header the application appended with extendService().
        self::assertSame(
            [
                'email:Acme BV:Invoice ACME-INV-01001',
                'webhook:Acme BV:X-Invoicing-Source,X-Invoicing-App',
            ],
            (new NotificationFacade())->deliveries(),
        );

        $receipt = (new PaymentApi())->pay($invoice->getNumber(), $invoice->getGrossCents(), 'sepa');

        self::assertSame('api.acme-pay.test', $receipt->endpoint);
        self::assertSame('sepa', $receipt->method);
    }

    /**
     * The EU layer also turns on the country check, which nothing else does.
     */
    public function test_the_eu_region_refuses_to_invoice_outside_the_supported_countries(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=eu');

        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-us', 'Acme Inc', 'US');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not invoice into "US"');

        (new BillingFacade())->issueInvoice('acme-us', 10_000);
    }

    /**
     * Issuing one invoice looks the customer up more than once. The facade
     * method carries `#[Cacheable]` with a per-reference key, so the store
     * behind it is read once and the second reference is a separate entry.
     */
    public function test_the_customer_lookup_is_served_from_the_method_cache(): void
    {
        ReferenceApp::bootstrap();

        $customers = new CustomerFacade();
        $customers->registerCustomer('acme-nl', 'Acme BV', 'NL');
        $customers->registerCustomer('acme-es', 'Acme SL', 'ES');

        $customers->findCustomer('acme-nl');
        $customers->findCustomer('acme-nl');
        $customers->findCustomer('acme-nl');

        self::assertSame(1, $customers->repositoryReadCount());

        $customers->findCustomer('acme-es');

        self::assertSame(2, $customers->repositoryReadCount(), 'a different customer is a different entry');
    }

    /**
     * The tagged validators refuse before anything is issued, and the one that
     * needs another module is the one Billing added to the tag itself.
     */
    public function test_an_invoice_for_an_unregistered_customer_is_refused(): void
    {
        ReferenceApp::bootstrap();

        self::assertSame(
            ['positive-amount', 'non-empty-reference', 'maximum-amount', 'registered-customer'],
            (new BillingFacade())->validatorNames(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('customer "ghost" is not registered');

        (new BillingFacade())->issueInvoice('ghost', 1_000);
    }

    /**
     * Swapping a whole module out at the seam Gacela provides for it.
     *
     * Billing is not told, and this time it could not be: it dispatches an event
     * and never names the module that reacts. What the override replaces is what
     * the listener in `gacela.php` resolves -- which is why that listener asks
     * the locator for the facade instead of constructing one.
     */
    public function test_a_stub_notification_facade_takes_the_place_of_the_real_one(): void
    {
        ReferenceApp::bootstrap();

        $silent = new SilentNotificationFacade();
        Gacela::overrideExistingResolvedClass(NotificationFacade::class, $silent);

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        self::assertSame(['ACME-INV-01001'], $silent->suppressed());
    }

    /**
     * The module-to-module acceptance criterion, from the outside.
     *
     * `Billing` announces that an invoice exists and `Notification` sends the
     * mail, with no import, no injected facade and no name of `Notification`
     * anywhere in `Billing` -- see
     * {@see InvoicingSliceTest::test_billing_depends_on_customer_and_nothing_else},
     * which asserts that half against the module graph the analysers and
     * `debug:graph --check --rules` read.
     *
     * A second listener on the same event is a line in `gacela.php` and no
     * change to `Billing` at all, which is what the decoupling buys.
     */
    public function test_the_module_that_reacts_to_an_invoice_is_never_named_by_the_one_that_issues_it(): void
    {
        $heard = [];

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use (&$heard): void {
            $config->registerSpecificListener(
                InvoiceIssuedEvent::class,
                static function (InvoiceIssuedEvent $event) use (&$heard): void {
                    $heard[] = $event->toString();
                },
            );
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        // The listener this test added, beside the one `gacela.php` registers:
        // both ran, and neither is known to Billing.
        self::assertSame(['Invoice ACME-INV-01001 issued: 12100 EUR due'], $heard);
        self::assertSame(
            ['email:Acme BV:Invoice ACME-INV-01001'],
            (new NotificationFacade())->deliveries(),
        );
    }

    /**
     * The other side of the guard: an application that registers no listener at
     * all pays nothing for the announcement, because the event is never built.
     * `disableEventListeners()` is the bluntest way to arrange that.
     */
    public function test_an_invoice_is_issued_with_no_listener_and_nothing_is_announced(): void
    {
        ReferenceApp::bootstrap(static function (GacelaConfig $config): void {
            $config->disableEventListeners();
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        self::assertSame('ACME-INV-01001', $invoice->getNumber());
        self::assertSame([], (new NotificationFacade())->deliveries());
    }

    /**
     * A project's own event, on the host's own bus: the application hands Gacela
     * a PSR-14 dispatcher, and `InvoiceIssuedEvent` arrives on it beside the
     * framework's events. Nothing in `Billing` changes -- it dispatches into
     * whatever the application installed.
     */
    public function test_the_applications_own_event_reaches_a_supplied_psr14_bus(): void
    {
        $bus = new RecordingPsr14Bus();

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use ($bus): void {
            $config->setEventDispatcher($bus);
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        self::assertSame(
            ['Invoice ACME-INV-01001 issued: 12100 EUR due'],
            $bus->descriptionsOf(InvoiceIssuedEvent::class),
        );
        // The framework's events reach it too: one dispatcher, both kinds.
        self::assertContains(GacelaBootstrapFinishedEvent::class, $bus->receivedClasses());
    }

    /**
     * Both halves of the configuration contribute, which is the arrangement
     * #866 was about: the closure registers a generic listener and `gacela.php`
     * registers a specific one, and both are heard.
     */
    public function test_listeners_from_the_closure_and_from_the_gacela_file_both_fire(): void
    {
        $fromClosure = [];

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use (&$fromClosure): void {
            $config->registerGenericListener(static function (GacelaEventInterface $event) use (&$fromClosure): void {
                $fromClosure[] = $event::class;
            });
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        self::assertNotSame([], $fromClosure, 'the listener registered in the bootstrap closure never fired');
        self::assertNotSame([], ResolverActivityLog::entries(), 'the listener registered in gacela.php never fired');
    }

    /**
     * An application may hand Gacela the dispatcher instead of letting it build
     * one -- the way these events reach a project's own bus.
     *
     * This application's `gacela.php` registers a listener of its own, which is
     * exactly the combination that used to throw the handover away: the merge
     * built a `ConfigurableEventDispatcher` to hold the listener and installed
     * it over the top, and a `final` class is one an application's dispatcher
     * can never be. The bus received the two events dispatched before the merge
     * and then nothing (#888).
     */
    public function test_the_application_can_hand_over_its_own_event_dispatcher(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use ($dispatcher): void {
            $config->setEventDispatcher($dispatcher);
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        self::assertContains(GacelaBootstrapStartedEvent::class, $dispatcher->received());
        // Dispatched after `gacela.php` has been merged in, so it only arrives
        // if the handover survived the merge.
        self::assertContains(GacelaBootstrapFinishedEvent::class, $dispatcher->received());
        self::assertContains(ResolvedClassCreatedEvent::class, $dispatcher->received());
    }

    /**
     * `setEventDispatcher()` says what delivers the events and
     * `registerSpecificListener()` says what must run. Both were asked for, so
     * both happen -- the listener in `gacela.php` runs *and* the application's
     * bus is told.
     */
    public function test_a_supplied_dispatcher_composes_with_the_listeners_in_the_gacela_file(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use ($dispatcher): void {
            $config->setEventDispatcher($dispatcher);
        });

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');

        self::assertNotSame([], ResolverActivityLog::entries(), 'the listener registered in gacela.php never fired');
        // The same event the `gacela.php` listener is registered against, so
        // one dispatch has to reach both -- which is the composition. Before
        // it, the dispatcher built to hold that listener replaced the supplied
        // one, and only one of the two ever heard about a resolver event.
        self::assertContains(ResolvedClassCreatedEvent::class, $dispatcher->received());
    }

    /**
     * `config/*.php` matches the environment files beside `app.php` as well, so
     * the base layer has to exclude them by name -- otherwise every environment
     * file is read on every run, before the chain that selects one is applied.
     */
    public function test_the_development_environment_reads_the_base_configuration(): void
    {
        ReferenceApp::bootstrap();

        self::assertSame('sandbox.acme-pay.test', (new PaymentApi())->gatewayEndpoint());
        self::assertSame('EUR', (new BillingFacade())->currency());
        self::assertSame(['email'], (new NotificationFacade())->channelNames());
    }

    /**
     * `payment.default_method` is set in `config/app-prod.php` and in no other
     * layer, so outside production there is nothing to read it from and the
     * schema's declared default -- `card` -- answers instead.
     *
     * This is the shape #889 was about: a key with no base value has nothing to
     * overwrite it, so the production value survived the merge and a developer
     * settled by SEPA. Asserted on the receipt rather than on the configuration,
     * and on a key the base layer does not mention, so it cannot come out right
     * by the order `glob()` happened to return the three files in.
     */
    public function test_a_key_only_production_sets_does_not_reach_the_development_environment(): void
    {
        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        $receipt = (new PaymentApi())->pay($invoice->getNumber(), $invoice->getGrossCents());

        self::assertSame('card', $receipt->method);
    }

    /**
     * The other half: in production the key is there, and it is the layer that
     * carries it -- not the base file, which never mentions it.
     */
    public function test_a_key_only_production_sets_is_read_in_production(): void
    {
        putenv('APP_ENV=prod');
        putenv('APP_REGION=eu');

        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        $receipt = (new PaymentApi())->pay($invoice->getNumber(), $invoice->getGrossCents());

        self::assertSame('sepa', $receipt->method);
    }

    /**
     * One interface, two answers, decided by who is asking: the contextual
     * binding gives Payment a retry policy that nothing else in the application
     * gets.
     */
    public function test_only_the_payment_module_retries(): void
    {
        ReferenceApp::bootstrap();

        self::assertSame(3, (new PaymentApi())->retryAttempts());
        self::assertSame(1, (new NotificationFacade())->retryAttempts());
    }

    /**
     * A factory service is built again on every resolution, which is what makes
     * two captures two attempts rather than one repeated.
     */
    public function test_every_capture_gets_its_own_attempt_id(): void
    {
        ReferenceApp::bootstrap();

        (new CustomerFacade())->registerCustomer('acme-nl', 'Acme BV', 'NL');
        $invoice = (new BillingFacade())->issueInvoice('acme-nl', 10_000);

        $payments = new PaymentApi();
        $first = $payments->pay($invoice->getNumber(), 6_000, 'card');
        $second = $payments->pay($invoice->getNumber(), 6_100, 'sepa');

        self::assertNotSame($first->attemptId, $second->attemptId);
    }

    /**
     * A caller may pin configuration values at bootstrap without touching a
     * file -- what a test does to make one number the only thing that varies,
     * and what a host framework does when its own configuration is the source
     * of truth.
     */
    public function test_a_caller_can_pin_configuration_values_at_bootstrap(): void
    {
        ReferenceApp::bootstrap(static function (GacelaConfig $config): void {
            $config->addAppConfigKeyValues([
                'billing.currency' => 'USD',
                'billing.vat_rate_bp' => 0,
            ]);
        });

        (new CustomerFacade())->registerCustomer('acme-us', 'Acme Inc', 'US');

        $invoice = (new BillingFacade())->issueInvoice('acme-us', 10_000);

        self::assertSame('USD', $invoice->getCurrency());
        self::assertSame(0, $invoice->getTaxCents());
    }

    /**
     * A cached lookup has to be forgettable, or a customer who changes their
     * name stays changed for the lifetime of the process.
     */
    public function test_clearing_the_method_cache_makes_the_next_lookup_read_the_store(): void
    {
        ReferenceApp::bootstrap();

        $customers = new CustomerFacade();
        $customers->registerCustomer('acme-nl', 'Acme BV', 'NL');

        $customers->findCustomer('acme-nl');
        $customers->findCustomer('acme-nl');
        self::assertSame(1, $customers->repositoryReadCount());

        CustomerFacade::clearMethodCacheFor('findCustomer');

        $customers->findCustomer('acme-nl');
        self::assertSame(2, $customers->repositoryReadCount());
    }

    /**
     * The handler registry answers one key with one handler, and the payment
     * methods this deployment supports are the keys it was declared with.
     */
    public function test_the_supported_payment_methods_are_the_registered_ones(): void
    {
        ReferenceApp::bootstrap();

        self::assertSame(['card', 'sepa'], (new PaymentApi())->supportedMethods());
    }
}
