<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Infrastructure;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    /**
     * @return array<string,int>
     */
    public function createDummyArray(): array
    {
        return [
            'from-infrastructure-factory' => 3,
        ];
    }
}
