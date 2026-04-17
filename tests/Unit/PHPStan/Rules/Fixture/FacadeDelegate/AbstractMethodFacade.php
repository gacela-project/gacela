<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

use Gacela\Framework\AbstractFacade;

abstract class AbstractMethodFacade extends AbstractFacade
{
    abstract public function contract(): void;
}
