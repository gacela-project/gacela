<?php

declare(strict_types=1);

/**
 * Read when `APP_ENV=prod`, on top of `app.php`.
 */
return [
    'payment.gateway_endpoint' => 'api.acme-pay.test',
    'billing.digital_surcharge_bp' => 300,
];
