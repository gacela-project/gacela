<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public const STR = 'from factory';

    public function createString(): string
    {
        return self::STR;
    }
}
