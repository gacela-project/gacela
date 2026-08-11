<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use ArrayObject;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

/**
 * Registers through singleton() only: real registrations, but not the store
 * `extend()`'s queue drains from -- an extension on either id silently never
 * applies (#683), so the check must flag them.
 */
final class BindOnlyProvider extends AbstractProvider
{
    public const SINGLETON_ID = 'bound.singleton.id';

    public function provideModuleDependencies(Container $container): void
    {
        $container->singleton(self::SINGLETON_ID, static fn (): ArrayObject => new ArrayObject());
    }
}
