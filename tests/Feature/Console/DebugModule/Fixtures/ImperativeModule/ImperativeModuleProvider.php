<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule\Fixtures\ImperativeModule;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Registers everything imperatively and declares no `#[Provides]`, which is how
 * a Provider was written before the attribute existed and how most still are.
 *
 * Deliberately without the attribute: this module exists to hold the line that
 * `debug:module` reports what `#[Provides]` declares and not what `set()`
 * registers.
 */
final class ImperativeModuleProvider extends AbstractProvider
{
    public const GATEWAY = 'IMPERATIVE_GATEWAY';

    public function provideModuleDependencies(Container $container): void
    {
        $container->set(self::GATEWAY, 'the gateway');
    }
}
