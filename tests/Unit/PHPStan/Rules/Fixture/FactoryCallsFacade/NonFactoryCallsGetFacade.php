<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FactoryCallsFacade;

use stdClass;

final class NonFactoryCallsGetFacade
{
    public function doIt(): void
    {
        $this->getFacade()->doStuff();
    }

    public function getFacade(): stdClass
    {
        return new stdClass();
    }
}
