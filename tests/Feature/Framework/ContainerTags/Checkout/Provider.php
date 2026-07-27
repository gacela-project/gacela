<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContainerTags\Checkout;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;

final class Provider extends AbstractProvider
{
    public const VALIDATORS = 'CHECKOUT_VALIDATORS';

    public const TAG = 'validators';

    public function provideModuleDependencies(Container $container): void
    {
        // Adds to the app-wide tag, for this module's container only.
        $container->tag(CardValidator::class, self::TAG);

        $container->set(
            self::VALIDATORS,
            static fn (): array => [...$container->tagged(self::TAG)],
        );
    }
}
