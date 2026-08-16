<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap\RefuseSandboxGateway;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging\ProductionNotificationChannels;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Packaging\StrictTaxRules;

/**
 * Read on top of `gacela.php` when `APP_ENV=prod`.
 *
 * Only the differences: everything not restated here is what the base file
 * decided, which is what keeps the two from drifting into two descriptions of
 * the same application.
 */
return static function (GacelaConfig $config): void {
    // A deployment resolves the same classes for its whole life, so the cost of
    // looking them up is paid once, at warm-up, instead of on every request.
    $config->enableFileCache('data/cache');

    // The deploy gate has already run `validate:config`; re-checking on every
    // boot only moves the same answer to a worse moment.
    $config->validateConfigSchemaOnBoot(false);

    // Customer records change rarely in production and are read constantly.
    $config->addAppConfigKeyValue('customer.lookup_cache_ttl', 900);

    // The obligations that only apply where the money is real.
    $config->extendGacelaConfigs([
        StrictTaxRules::class,
        ProductionNotificationChannels::class,
    ]);

    // The last thing between a deploy and a very expensive afternoon.
    $config->addPlugin(RefuseSandboxGateway::class);
};
