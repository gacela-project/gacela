<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Db;

use GacelaTest\Feature\Framework\CustomServiceAware\Module\Facade;

final class CustomHelloRepository
{
    private Facade $facade;

    public function __construct(Facade $facade)
    {
        $this->facade = $facade;
    }

    public function findNameById(int $id): string
    {
        $bye = $this->facade->sayGoodbye();

        return "fake-name(id:{$id}), and {$bye}";
    }
}
