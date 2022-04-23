<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServiceAware\Module\Infrastructure\Db;

use Gacela\Framework\AbstractCustomService;
use GacelaTest\Feature\Framework\CustomServiceAware\Module\Facade;

final class Repository extends AbstractCustomService
{
    public function findNameById(int $id): string
    {
        return "fake-name(id:{$id})";
    }

    protected function facadeClass(): string
    {
        return Facade::class;
    }
}
