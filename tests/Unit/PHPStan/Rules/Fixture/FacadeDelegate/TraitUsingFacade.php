<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeDelegate;

use Gacela\Framework\AbstractFacade;

final class TraitUsingFacade extends AbstractFacade
{
    use LogicTrait;
}
