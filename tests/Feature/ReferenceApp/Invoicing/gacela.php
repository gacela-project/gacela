<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Event\ClassResolver\AbstractGacelaClassResolverEvent;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingProvider;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\CountryRegistry;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceNumbering;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\InvoiceRecord;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\StandardVatCalculator;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxCalculatorInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Tax\TaxRateTable;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\NonEmptyReferenceValidator;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\PositiveAmountValidator;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event\InvoiceIssuedEvent;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Health\LedgerHealthCheck;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\Domain\CustomerProfile;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\EmailChannel;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Domain\Channel\NotificationChannelInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\Infrastructure\ResolverActivityLog;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationFacade;
use GacelaTest\Feature\ReferenceApp\Invoicing\Notification\NotificationProvider;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\AbstractGateway;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\AttemptId;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method\CardPaymentHandler;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method\PaymentMethodHandlerInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\Method\SepaPaymentHandler;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\PaymentBuilder;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap\ApplyCacheableTtlOverrides;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap\RegisterCacheableStorage;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Clock\ClockInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging\InvoicingPackageDefaults;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\RetryPolicyInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\ThreeAttemptPolicy;

/**
 * The composition root of the invoicing application.
 *
 * Everything the five modules share is decided here and nowhere else: where
 * configuration lives, which implementation answers an interface, which
 * extension points are filled, and what the application promises its
 * configuration will contain.
 *
 * This file composes *with* the closure passed to `Gacela::bootstrap()` rather
 * than replacing it -- the closure is the base and this file merges onto it, so
 * a host that registers a listener at bootstrap keeps it and the ones below run
 * too.
 */
return static function (GacelaConfig $config): void {
    // ------------------------------------------------------------------
    // Where things are
    // ------------------------------------------------------------------

    $config->addAppConfig('config/*.php', 'config/local.php');
    $config->setProjectNamespaces(['GacelaTest\Feature\ReferenceApp\Invoicing']);

    // The five modules, named rather than inferred: everything else under the
    // application root is configuration, wiring or a shared kernel, and
    // scanning it would only make `list:modules` slower for the same answer.
    $config->setAppModulePaths([
        'Billing',
        'Customer',
        'Notification',
        'Payment',
        'Reporting',
    ]);

    // Off here, on in production. A developer wants the resolver to notice the
    // class they just renamed; a deployment wants it to stop looking.
    $config->setFileCache(false, 'data/cache');

    // A second dimension past APP_ENV: `config/app-prod-eu.php` refines
    // `config/app-prod.php`, and an unset APP_REGION ends the chain there.
    $config->addConfigDimension('APP_REGION');

    // ------------------------------------------------------------------
    // What the configuration must contain
    // ------------------------------------------------------------------

    $config->declareConfigSchema([
        'billing.currency' => ConfigType::string()->required()
            ->describe('ISO 4217 code every invoice is issued in'),
        'billing.vat_rate_bp' => ConfigType::int()->required()
            ->describe('standard VAT rate, in basis points'),
        'billing.digital_surcharge_bp' => ConfigType::int()->required()
            ->describe('digital services surcharge, in basis points'),
        'billing.retention_years' => ConfigType::int()->required(),
        'billing.refuse_unknown_countries' => ConfigType::bool()->required(),
        'billing.supported_countries' => ConfigType::array()->required(),
        'customer.default_tier' => ConfigType::string()->default('standard'),
        'customer.lookup_cache_ttl' => ConfigType::int()->default(300)
            ->describe('seconds a customer lookup may be served from the method cache'),
        'notification.subject_prefix' => ConfigType::string()->required(),
        'payment.gateway_endpoint' => ConfigType::string()->required(),
        'payment.default_method' => ConfigType::string()->default('card'),
    ]);

    // Cheap, and it turns "why is this key null" into a failure at the first
    // boot after the mistake. Production switches it off in `gacela-prod.php`,
    // where the deploy gate has already answered the question.
    $config->validateConfigSchemaOnBoot();

    // ------------------------------------------------------------------
    // The shapes the modules exchange
    // ------------------------------------------------------------------

    $config->declareDtoSchema(CustomerProfile::class, [
        'reference' => DtoType::string()->required()->describe('the id a customer is billed under'),
        'name' => DtoType::string()->required(),
        'countryCode' => DtoType::string()->required()->describe('ISO 3166-1 alpha-2'),
        'tier' => DtoType::string()->default('standard'),
        'taxId' => DtoType::string(),
    ]);

    $config->declareDtoSchema(InvoiceRecord::class, [
        'number' => DtoType::string()->required(),
        'customerReference' => DtoType::string()->required(),
        'netCents' => DtoType::int()->required(),
        'taxCents' => DtoType::int()->required(),
        'grossCents' => DtoType::int()->required(),
        'currency' => DtoType::string()->required(),
        'issuedOn' => DtoType::string()->required()->describe('YYYY-MM-DD'),
    ]);

    // ------------------------------------------------------------------
    // The kinds this project resolves
    // ------------------------------------------------------------------

    // A fifth pillar. "Which gateway is this module talking to" has exactly one
    // answer per module and belongs beside the Factory, not in a binding.
    $config->addResolvableType('Gateway', AbstractGateway::class, ['Gateway']);

    // The Payment module came from the codebase that preceded this one and kept
    // its vocabulary: PaymentApi, PaymentBuilder, PaymentSettings,
    // PaymentDependencyProvider. Four declarations here are cheaper, and far
    // less risky, than the rename that would otherwise be the price of entry.
    $config->addSuffixTypeFacade('Api');
    $config->addSuffixTypeFactory('Builder');
    $config->addSuffixTypeConfig('Settings');
    $config->addSuffixTypeProvider('DependencyProvider');

    // ------------------------------------------------------------------
    // Bindings
    // ------------------------------------------------------------------

    // The host tells this application what time it is; the key is agreed with
    // whoever calls `Gacela::bootstrap()`.
    $config->addBinding(ClockInterface::class, $config->getExternalService('clock'));

    // What this application would ship as a package: defaults a consumer gets
    // and can replace. The clock default declines, because the line above bound
    // one already.
    $config->extendGacelaConfig(InvoicingPackageDefaults::class);

    // Service wiring that is data rather than code, for the services the
    // application containers build. The retry policy lives there: it is a
    // default this application picks, not a decision the bootstrap makes, and
    // `NotificationFactory` takes it as a constructor argument.
    $config->loadDefinitions(__DIR__ . '/services.php');

    // Try once, everywhere except payments. A capture is worth retrying, and
    // nothing else in the application is.
    $config->when(PaymentBuilder::class)
        ->needs(RetryPolicyInterface::class)
        ->give(ThreeAttemptPolicy::class);

    // Both of the next two read configuration, which is not loaded yet while
    // this file is being evaluated, so both are closures called later.
    //
    // They differ in *who asks*. The rate table is a constructor argument of the
    // tax calculators, and a nested constructor argument is resolved by
    // autowiring, which consults bindings and not the lazy or factory
    // registries -- so it has to be a binding. The country registry is fetched
    // by id from the Provider, where a lazy registration does apply, and an
    // application that never issues an invoice never builds it.
    $config->addBinding(TaxRateTable::class, static fn (): TaxRateTable => new TaxRateTable(
        Config::getInstance()->getInt('billing.vat_rate_bp'),
        Config::getInstance()->getInt('billing.digital_surcharge_bp'),
    ));

    $config->addLazy(CountryRegistry::class, static function (): CountryRegistry {
        /** @var list<string> $supported */
        $supported = Config::getInstance()->getArray('billing.supported_countries');

        return new CountryRegistry(
            $supported,
            Config::getInstance()->getBool('billing.refuse_unknown_countries'),
        );
    });

    // One attempt per capture. Resolved by id from the Factory, which is where
    // "a new instance every time" is a promise the container keeps.
    $config->addFactory(AttemptId::class, static fn (): AttemptId => new AttemptId());

    // A callback the container must hand back rather than call.
    $config->addProtected(
        BillingProvider::NUMBER_FORMAT,
        static fn (string $prefix, int $sequence): string => \sprintf('%s-%05d', $prefix, $sequence),
    );

    // The short id operators and `debug:container` use for the same instance
    // the modules type-hint as an interface.
    $config->addAlias('clock', ClockInterface::class);

    // ------------------------------------------------------------------
    // Extension points
    // ------------------------------------------------------------------

    // Tax is a stack because rules compose: production adds a surcharge to this
    // one instead of replacing it. See `Shared/Packaging/StrictTaxRules.php`.
    $config->addPluginStack(TaxCalculatorInterface::class, [StandardVatCalculator::class]);

    // Email everywhere; production adds the webhook.
    $config->addPluginStack(NotificationChannelInterface::class, [EmailChannel::class]);

    // Keyed dispatch, not "every implementation of": paying by card is one
    // question with one answer.
    $config->addHandlerRegistry(PaymentMethodHandlerInterface::class, [
        'card' => CardPaymentHandler::class,
        'sepa' => SepaPaymentHandler::class,
    ]);

    // The validators that are about invoices and belong to nobody in
    // particular. Billing adds the one that needs the Customer module in its
    // own Provider, where it stays local to that module's container.
    $config->tag(
        [PositiveAmountValidator::class, NonEmptyReferenceValidator::class],
        BillingProvider::VALIDATOR_TAG,
    );

    // Wherever the notification headers are registered, add ours.
    $config->extendService(
        NotificationProvider::HEADERS,
        static fn (array $headers): array => [...$headers, 'X-Invoicing-App' => 'reference'],
    );

    // The ledger this application writes into is shared with the rest of the
    // group, so every number it issues carries the entity that issued it.
    //
    // Only where *Billing's* Provider registers it: another module using the
    // string "NUMBER_PREFIX" is none of this extension's business.
    $config->extendProviderService(
        BillingProvider::class,
        BillingProvider::NUMBER_PREFIX,
        static fn (string $prefix): string => 'ACME-' . $prefix,
    );

    // Idempotent on purpose: the hook runs once per resolution, not once per
    // instance, so setting where the sequence starts is safe where advancing it
    // would not be.
    $config->afterResolving(
        InvoiceNumbering::class,
        static fn (InvoiceNumbering $numbering) => $numbering->startFrom(1_000),
    );

    // ------------------------------------------------------------------
    // Observability
    // ------------------------------------------------------------------

    // One registration for every resolver event there is: the dispatcher
    // matches by inheritance, and the callable stays typed against the parent
    // so analysis can check it.
    $config->registerSpecificListener(
        AbstractGacelaClassResolverEvent::class,
        // Nothing from this file is captured: the log is process-wide, like the
        // resolver it watches.
        static fn (AbstractGacelaClassResolverEvent $event) => ResolverActivityLog::record($event),
    );

    // ------------------------------------------------------------------
    // What reacts to what
    // ------------------------------------------------------------------

    // Billing announces that an invoice exists; Notification sends the mail.
    // This line is the only place both modules are named, which is what lets
    // `Billing` be written without knowing `Notification` exists -- `debug:graph`
    // draws no edge between them, and the analysers agree.
    //
    // A second reaction -- Reporting updating a projection, an audit log -- is
    // another registration here and no change to Billing at all.
    //
    // Resolved through the locator rather than constructed: that is the path a
    // module double travels, so a test that replaces the Notification module
    // replaces the thing this listener reaches.
    $config->registerSpecificListener(
        InvoiceIssuedEvent::class,
        static function (InvoiceIssuedEvent $event): void {
            /** @var NotificationFacade $notifications */
            $notifications = Gacela::getRequired(NotificationFacade::class);
            $notifications->onInvoiceIssued($event);
        },
    );

    $config->addHealthCheck(LedgerHealthCheck::class);

    // ------------------------------------------------------------------
    // Bootstrap hooks
    // ------------------------------------------------------------------

    // In this order: the overrides below are about a store the first one
    // registers.
    $config->addPlugins([
        RegisterCacheableStorage::class,
        ApplyCacheableTtlOverrides::class,
    ]);
};
