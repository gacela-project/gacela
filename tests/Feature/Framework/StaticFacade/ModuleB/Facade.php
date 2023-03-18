<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory factory()
 * @method static Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public static function createStringFromStaticFactory(): string
    {
        return self::getFactory()->createString();
    }

    public function createStringFromNonStaticFactory(): string
    {
        return $this->factory()->createString();
    }
}
