<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * `#[Inject]` on the constructor itself: Laravel builds it, and the listener
 * must not call it a second time.
 */
final class InjectedConstructorConsumer
{
    public static int $constructed = 0;

    #[Inject]
    public function __construct(
        public readonly CountingService $service,
    ) {
        ++self::$constructed;
    }
}
