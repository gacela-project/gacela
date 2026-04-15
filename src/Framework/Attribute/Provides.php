<?php

declare(strict_types=1);

namespace Gacela\Framework\Attribute;

use Attribute;

/**
 * Marks a provider method as a container service factory.
 *
 * The annotated method's return value is registered under `$id` in the
 * Container; the method is wrapped in a lazy closure, so it is only invoked
 * when the service is resolved. If the method signature declares a
 * `Container` parameter, the Container is passed through on resolve.
 *
 * Example:
 * ```php
 * use Gacela\Framework\AbstractProvider;
 * use Gacela\Framework\Attribute\Provides;
 * use Gacela\Framework\Container\Container;
 *
 * final class MyProvider extends AbstractProvider
 * {
 *     #[Provides('COMMANDS')]
 *     public function commands(): array
 *     {
 *         return [new MyCommand()];
 *     }
 *
 *     #[Provides('EXTERNAL_FACADE')]
 *     public function externalFacade(Container $container): OtherFacade
 *     {
 *         return $container->getLocator()->get(OtherFacade::class);
 *     }
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Provides
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
