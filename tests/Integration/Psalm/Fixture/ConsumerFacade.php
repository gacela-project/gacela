<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm\Fixture;

use Gacela\Framework\AbstractFacade;

final class ConsumerFacade extends AbstractFacade
{
    public function knownMethod(): string
    {
        return 'known';
    }
}
