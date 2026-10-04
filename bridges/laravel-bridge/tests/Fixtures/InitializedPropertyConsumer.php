<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * Construction sets the property. Injection fills what construction left
 * empty; it does not overrule what construction decided.
 */
final class InitializedPropertyConsumer
{
    public const FROM_CONSTRUCTOR = 'from-constructor';

    #[Inject]
    private ?CountingService $service;

    public function __construct()
    {
        $this->service = new CountingService(self::FROM_CONSTRUCTOR);
    }

    public function service(): ?CountingService
    {
        return $this->service;
    }
}
