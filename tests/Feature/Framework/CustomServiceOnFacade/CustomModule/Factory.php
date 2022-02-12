<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceOnFacade\CustomModule;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    /**
     * @return array<string,int>
     */
    public function createDummyArray(): array
    {
        return [
            'from-factory' => 1,
        ];
    }
}
