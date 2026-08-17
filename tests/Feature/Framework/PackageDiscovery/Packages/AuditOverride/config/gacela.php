<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditSinkInterface;

/**
 * The same key and the same binding `gacela-fixture/audit-trail` declares, with
 * the other answer -- which is what an add-on for another package looks like.
 *
 * Whichever of the two Composer installed later is the one an application gets,
 * which is why a package that *needs* to win must not ship a declaration and
 * hope: it should give the consuming project something to call.
 */
return static function (GacelaConfig $config): void {
    $config->addAppConfigKeyValue('audit.enabled', false);

    $config->addBinding(
        AuditSinkInterface::class,
        static fn (): AuditSinkInterface => new class() implements AuditSinkInterface {
            public function label(): string
            {
                return 'the later-installed package decided';
            }
        },
    );
};
