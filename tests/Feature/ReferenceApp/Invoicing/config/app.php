<?php

declare(strict_types=1);

/**
 * The base layer every environment starts from.
 *
 * Keys are flat and dotted rather than nested, because that is how they are
 * read back -- `Config::get('billing.currency')` looks up one key, and a
 * nested array would only be reachable as a whole.
 *
 * Only the keys that mean something in every environment. A key one deployment
 * sets and the others have no answer for belongs in that deployment's layer and
 * nowhere else -- `payment.default_method` is set in `app-prod.php` alone, and
 * outside production it is simply not there.
 *
 * `addAppConfig('config/*.php')` matches the two environment files beside this
 * one as well, and the loader excludes them from this layer by name: a file
 * whose basename is this one's plus `-<suffix>` is read only when the
 * environment-and-dimensions chain resolves to that suffix. `doctor` names
 * every file it excludes that way.
 */
return [
    'billing.currency' => 'EUR',
    'billing.vat_rate_bp' => 2100,
    'billing.digital_surcharge_bp' => 0,
    'billing.retention_years' => 7,
    'billing.refuse_unknown_countries' => false,
    'billing.supported_countries' => ['DE', 'ES', 'NL'],

    'notification.subject_prefix' => 'Invoice',

    'payment.gateway_endpoint' => 'sandbox.acme-pay.test',
];
