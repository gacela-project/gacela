<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @method static Factory factory()
 * @method Factory getFactory()
 */
final class Facade extends AbstractFacade
{
    public static function createStringFromStaticFactory(): string
    {
        return self::factory()->createString();
    }

    public function createStringFromNonStaticFactory(): string
    {
        return $this->getFactory()->createString();
    }
}
