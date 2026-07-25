<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\Fixture;

use Gacela\Framework\AbstractFacade;

final class MappedFacade extends AbstractFacade
{
    public function knownMethod(): string
    {
        return 'known';
    }
}
