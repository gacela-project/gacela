<?php

declare(strict_types=1);

use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingProvider;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\MaximumAmountValidator;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\RetryPolicyInterface;
use GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Resilience\SingleAttemptPolicy;

/**
 * Wiring that is data rather than code, read by `loadDefinitions()`.
 *
 * Kept out of `config/`, which is globbed for *configuration values*: a file of
 * service definitions sitting in there would be merged into the config array
 * and turn up in `debug:config` as a set of keys nobody declared.
 *
 * The tag below is the same one `gacela.php` fills with two validators. Tags
 * accumulate rather than override, which is what lets a generated or
 * environment-specific file contribute to a group it did not declare.
 *
 * The retry policy is a constructor argument of `NotificationFactory`, which
 * the class resolver builds -- and a definition reaches a pillar constructor
 * exactly as `addBinding()` does, so the choice between the two is about where
 * the declaration reads best rather than about where it will be seen.
 */
return [
    MaximumAmountValidator::class => [
        'singleton' => MaximumAmountValidator::class,
        'tags' => [BillingProvider::VALIDATOR_TAG],
    ],
    RetryPolicyInterface::class => ['singleton' => SingleAttemptPolicy::class],
];
