<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractFacade;

/**
 * @method Factory getFactory()
 * @method static Factory factory()
 */
final class Facade extends AbstractFacade
{
    public static function informalGreet(string $name): string
    {
        return self::factory()
            ->createInformalGreeter()
            ->greet($name);
    }

    public function formalGreet(string $name): string
    {
        return $this->getFactory()
            ->createFormatGreeter()
            ->greet($name);
    }
}
