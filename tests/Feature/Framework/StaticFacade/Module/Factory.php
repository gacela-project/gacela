<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\StaticFacade\Module\Domain\FormalGreeter;
use GacelaTest\Feature\Framework\StaticFacade\Module\Domain\GreeterInterface;
use GacelaTest\Feature\Framework\StaticFacade\Module\Domain\InformalGreeter;

/**
 * @method Config getConfig()
 * @method static Config config()
 */
final class Factory extends AbstractFactory
{
    public function createInformalGreeter(): GreeterInterface
    {
        return new InformalGreeter();
    }

    public function createFormatGreeter(): GreeterInterface
    {
        return new FormalGreeter();
    }
}
