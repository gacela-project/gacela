<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\User;

use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ReferenceBase;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ReferenceContract;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ReferenceMarker;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ReferenceThing;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ReferenceTrait;
use GacelaTest\Unit\PHPStan\Rules\Fixture\CrossModule\Shop\Domain\ShopException;

final class NamedReferences extends ReferenceBase implements ReferenceContract
{
    use ReferenceTrait;

    #[ReferenceMarker]
    public function check(object $value, callable $run): bool
    {
        try {
            $run();
        } catch (ShopException) {
        }

        return $value instanceof ReferenceThing;
    }
}
