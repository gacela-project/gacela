<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module2;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module2\Application\GacelaUseCase;

final class Factory extends AbstractFactory
{
    public function createUseCase(): GacelaUseCase
    {
        return new GacelaUseCase();
    }
}
