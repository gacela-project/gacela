<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\CrossModuleFixture\User;

use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ReferenceBase;
use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ReferenceContract;
use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ReferenceMarker;
use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ReferenceThing;
use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ReferenceTrait;
use GacelaTest\Integration\Psalm\CrossModuleFixture\Shop\Domain\ShopException;

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
