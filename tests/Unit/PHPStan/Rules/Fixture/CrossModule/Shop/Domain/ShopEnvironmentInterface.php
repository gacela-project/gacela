<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain;

/**
 * The shape a project names in `ignoreReceivers`: a contract it treats as
 * public, whatever module it lives in.
 */
interface ShopEnvironmentInterface
{
    public function name(): string;
}
