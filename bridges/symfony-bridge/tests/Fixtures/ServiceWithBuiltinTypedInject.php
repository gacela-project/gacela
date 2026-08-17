<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge\Fixtures;

use Gacela\Container\Attribute\Inject;

/**
 * `#[Inject]` on a builtin-typed parameter with no implementation override.
 * There is no class to resolve, so the pass has to leave the argument alone
 * rather than ask the container for a service called "string".
 */
final class ServiceWithBuiltinTypedInject
{
    public function __construct(
        #[Inject] public readonly string $name,
    ) {
    }
}
