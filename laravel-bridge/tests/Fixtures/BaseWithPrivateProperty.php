<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

abstract class BaseWithPrivateProperty
{
    #[Inject]
    private CountingService $baseService;

    public function baseService(): CountingService
    {
        return $this->baseService;
    }
}
