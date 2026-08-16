<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\GacelaEventInterface;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Infrastructure\ResolverActivityLog;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\PaymentApi;
use GacelaTest\Feature\ReferenceApp\Invoicing\Reporting\ReportingFacade;
use GacelaTest\Feature\ReferenceApp\Support\RecordingEventDispatcher;
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
     * Swapping a whole module out at the seam Gacela provides for it. Billing
     * reaches notifications through `#[ServiceMap]` and is not told.
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
     */
    public function test_the_application_can_hand_over_its_own_event_dispatcher(): void
    {
        $dispatcher = new RecordingEventDispatcher();

        ReferenceApp::bootstrap(static function (GacelaConfig $config) use ($dispatcher): void {
            $config->setEventDispatcher($dispatcher);
        });

        self::assertContains(GacelaBootstrapStartedEvent::class, $dispatcher->received());
    }

    /**
     * Every value the second environment reads is a value the first one already
     * had, so a key can only be refined and never introduced -- which is what
     * keeps `config/*.php`, a pattern that also matches the environment files,
     * from letting a production value reach a developer.
     */
    public function test_the_development_environment_reads_the_base_configuration(): void
    {
        ReferenceApp::bootstrap();

        self::assertSame('sandbox.acme-pay.test', (new PaymentApi())->gatewayEndpoint());
        self::assertSame('EUR', (new BillingFacade())->currency());
        self::assertSame(['email'], (new NotificationFacade())->channelNames());
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
