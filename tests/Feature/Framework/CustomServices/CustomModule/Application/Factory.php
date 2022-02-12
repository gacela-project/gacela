<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Application;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    /**
     * @return array<string,int>
     */
    public function createDummyArray(): array
    {
        return [
            'from-application-factory' => 2,
        ];
    }
}
