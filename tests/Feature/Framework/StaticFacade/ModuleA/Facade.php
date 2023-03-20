<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\ModuleA;

use Gacela\Framework\AbstractFacade;

/**
 * @method static Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public static function createString(): string
    {
        return self::getFactory()->createString();
    }
}
