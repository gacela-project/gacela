<?php

declare(strict_types=1);

use Gacela\Framework\Bootstrap\GacelaConfig;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\StandardTaxRate;
use GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Shared\Tax\TaxRateInterface;

/**
 * A three-module application shaped like a real one: Ordering reaches Pricing
 * through its Provider and Shipping through `#[ServiceMap]`, and the tax rate
 * is an interface the composition root answers -- the three ways a slice has to
 * be able to replace a dependency.
 *
 * `Shared` is deliberately not in the module paths: it is a kernel, not a
 * module, which is what makes narrowing to one module a meaningful change.
 */
return static function (GacelaConfig $config): void {
    $config->setProjectNamespaces(['GacelaTest\Integration\Framework\Testing\ModuleSliceFixture']);
    $config->setAppModulePaths(['Ordering', 'Pricing', 'Shipping']);
    $config->addBinding(TaxRateInterface::class, StandardTaxRate::class);

    // CurrencyInterface is deliberately *not* bound here. It is asked for in
    // OrderingFactory's constructor, which the class resolver fills from the
    // bindings and from nothing else, so the module cannot be built until
    // somebody answers it -- the same contract the reference application has
    // with its host over the clock. A slice answers it with a double.
};
