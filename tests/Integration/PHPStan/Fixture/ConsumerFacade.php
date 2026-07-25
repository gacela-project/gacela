<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan\Fixture;

use Gacela\Framework\AbstractFacade;

/**
 * @extends AbstractFacade<ConsumerFactory>
 */
final class ConsumerFacade extends AbstractFacade
{
    public function knownMethod(): string
    {
        return $this->getFactory()->createName();
    }
}
