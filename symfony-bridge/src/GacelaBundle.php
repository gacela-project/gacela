<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\SymfonyBridge\DependencyInjection\GacelaExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Gacela inside a Symfony application.
 *
 * Register it and the four things every Symfony/Gacela project wires by hand
 * are done: the `#[Inject]` compiler pass, bootstrapping from the kernel, the
 * console commands, and warming the caches with Symfony's own `cache:warmup`.
 *
 * ```php
 * // config/bundles.php
 * return [
 *     Gacela\SymfonyBridge\GacelaBundle::class => ['all' => true],
 * ];
 * ```
 */
final class GacelaBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new GacelaInjectCompilerPass());
    }

    /**
     * Every boot bootstraps again, on purpose: a kernel can boot more than once
     * in one process, and the second boot's configuration is the one that
     * should be in force -- not whatever the first one left behind.
     */
    public function boot(): void
    {
        $container = $this->container;
        if (!$container instanceof \Symfony\Component\DependencyInjection\ContainerInterface || !$container->has(GacelaExtension::BOOTSTRAPPER_ID)) {
            return;
        }

        $bootstrapper = $container->get(GacelaExtension::BOOTSTRAPPER_ID);
        if ($bootstrapper instanceof GacelaBootstrapper) {
            $bootstrapper->bootstrap();
        }
    }
}
