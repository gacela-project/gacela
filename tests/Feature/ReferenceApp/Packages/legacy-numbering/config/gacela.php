<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingProvider;

/**
 * Never executed while `Invoicing/gacela.php` names this package in
 * `dontDiscover()`. It is here so that the refusal is a refusal of something
 * real: remove the opt-out and every invoice number in the application changes.
 */
return static function (GacelaConfig $config): void {
    $config->addProtected(
        BillingProvider::NUMBER_FORMAT,
        static fn (string $prefix, int $sequence): string => \sprintf('%s/%d', $prefix, $sequence),
    );
};
