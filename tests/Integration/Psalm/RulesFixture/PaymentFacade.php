<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\RulesFixture;

/**
 * Same, as an interface.
 */
interface PaymentFacade
{
    public function pay(): void;
}
