<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\Testing\ModuleDoubleFixture\Sealed\Domain\SealedGreeter;

/**
 * `final`, which is what `make:module` generates and what the rest of this
 * codebase prefers. Nothing can extend it, so the double for this module has to
 * be a standalone `AbstractFactory` -- which is the point of the test that uses
 * it. The neighbouring `GreetingFactory` is deliberately not final, so both
 * shapes are covered.
 */
final class SealedFactory extends AbstractFactory
{
    public function createGreeter(): SealedGreeter
    {
        return new SealedGreeter();
    }
}
