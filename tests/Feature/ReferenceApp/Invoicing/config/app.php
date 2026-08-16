<?php

declare(strict_types=1);

/**
 * The base layer every environment starts from.
 *
 * Keys are flat and dotted rather than nested, because that is how they are
 * read back -- `Config::get('billing.currency')` looks up one key, and a
 * nested array would only be reachable as a whole.
 *
 * Every key the application reads is named here, even where the value is the
 * uninteresting one. An environment layer then only ever *refines* a key this
 * file already established, which is what keeps `config/*.php` -- a pattern
 * that also matches the environment files themselves -- from letting a
 * production-only key reach a developer's machine.
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
