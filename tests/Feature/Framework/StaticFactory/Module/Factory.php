<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFactory\Module;

use Gacela\Framework\AbstractFactory;

/**
 * @method static Config config()
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    public static function createStringFromStaticFactory(): string
    {
        return self::config()->getString();
    }

    public function createStringFromNonStaticFactory(): string
    {
        return $this->getConfig()->getString();
    }
}
