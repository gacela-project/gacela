<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\ModuleB;

use Gacela\Framework\AbstractFacade;

/**
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
        return $this->getFactory()->createString();
    }
}
