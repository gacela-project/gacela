<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

final class UntypedPropertyConsumer
{
    #[Inject]
    private $service;

    public function service(): mixed
    {
        return $this->service;
    }
}
