<?php

declare(strict_types=1);

use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\BillingProvider;
use GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Domain\Validation\MaximumAmountValidator;

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
 * What does *not* belong here: anything a Facade, Factory, Config or Provider
 * asks for in its constructor. Those four are built by the class resolver,
 * which reads `addBinding()` and nothing else -- see `gacela.php`, where the
 * retry policy is bound for that reason.
 */
return [
    MaximumAmountValidator::class => [
        'singleton' => MaximumAmountValidator::class,
        'tags' => [BillingProvider::VALIDATOR_TAG],
    ],
];
