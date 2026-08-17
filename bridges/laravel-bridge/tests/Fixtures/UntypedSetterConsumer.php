<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class UntypedSetterConsumer
{
    private mixed $service = null;

    public function service(): mixed
    {
        return $this->service;
    }

    #[Inject]
    public function setService(mixed $service): void
    {
        $this->service = $service;
    }
}
