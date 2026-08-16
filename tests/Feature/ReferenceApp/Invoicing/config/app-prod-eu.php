<?php

declare(strict_types=1);

/**
 * Read when `APP_ENV=prod` *and* `APP_REGION=eu`, on top of `app-prod.php`.
 *
 * The second dimension is declared in `gacela.php` with `addConfigDimension()`.
 * An unset `APP_REGION` ends the chain here, and `app-prod.php` is the last
 * word instead.
 */
return [
    'billing.vat_rate_bp' => 1900,
    'billing.refuse_unknown_countries' => true,
];
