<?php

declare(strict_types=1);

/**
 * Read when `APP_ENV=prod`, on top of `app.php`.
 *
 * `payment.default_method` is set here and in no other layer: SEPA is what the
 * real gateway settles on, and outside production the key has no value at all --
 * the schema's declared default answers for it instead. Before #889 the base
 * pattern globbed this file on every run, so a developer read `sepa` too.
 */
return [
    'payment.gateway_endpoint' => 'api.acme-pay.test',
    'payment.default_method' => 'sepa',
    'billing.digital_surcharge_bp' => 300,
];
