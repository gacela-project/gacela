<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Console\Application\Doctor\Check\Fixtures;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Attribute\Provides;
use Gacela\Framework\Container\Container;

/**
 * The shape `#[Provides]`'s own documentation shows: a provided method that
 * declares a `Container` parameter, which the scanner passes through.
 *
 * Working code, so the check must not report it -- and the method beside it,
 * which asks for something the scanner cannot supply, must still be reported.
 */
final class ContainerProvidesProvider extends AbstractProvider
{
    public const FROM_CONTAINER = 'from-container';

    public const CONTAINER_AND_MORE = 'container-and-more';

    #[Provides(self::FROM_CONTAINER)]
    public function fromContainer(Container $container): string
    {
        return $container::class;
    }

    #[Provides(self::CONTAINER_AND_MORE)]
    public function containerAndMore(Container $container, string $unexpected): string
    {
        return $unexpected;
    }
}
