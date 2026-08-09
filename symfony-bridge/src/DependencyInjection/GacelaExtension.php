<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge\DependencyInjection;

use Gacela\Console\Infrastructure\Command\InitCommand;
use Gacela\SymfonyBridge\GacelaBootstrapper;
use Gacela\SymfonyBridge\GacelaCacheWarmer;
use Gacela\SymfonyBridge\GacelaCommands;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

use Symfony\Component\DependencyInjection\ServiceLocator;

use function sprintf;

/**
 * Turns `gacela.yaml` into the two services the bundle needs: the one that
 * boots Gacela, and the one that warms its caches.
 *
 * @psalm-type GacelaBundleConfig = array{
 *     enabled: bool,
 *     app_root_dir: string,
 *     cache_dir: ?string,
 *     file_cache: ?bool,
 *     project_namespaces: list<string>,
 *     external_services: array<string, string>,
 *     register_commands: bool,
 *     command_prefix: string
 * }
 */
final class GacelaExtension extends Extension
{
    public const BOOTSTRAPPER_ID = 'gacela.bootstrapper';

    public const CACHE_WARMER_ID = 'gacela.cache_warmer';

    /**
     * @param array<array-key, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{enabled: bool, app_root_dir: string, cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>, external_services: array<string, string>, register_commands: bool, command_prefix: string} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        if (!$config['enabled']) {
            return;
        }

        $this->registerBootstrapper($container, $config);
        $this->registerCacheWarmer($container);

        if ($config['register_commands']) {
            $this->registerCommands($container, $config);
        }
    }

    /**
     * Neither `getConfiguration()` nor `getAlias()` is overridden: Symfony finds
     * `Configuration` in this namespace and derives the `gacela` alias from this
     * class's own name, and a second answer to either would be one more place to
     * keep in step.
     *
     * @param GacelaBundleConfig $config
     */
    private function registerBootstrapper(ContainerBuilder $container, array $config): void
    {
        // A locator rather than the container: the bridge reaches exactly the
        // services the project listed, and nothing it did not.
        $locator = new Definition(ServiceLocator::class);
        $locator->setArguments([$this->serviceReferences($config['external_services'])]);
        $locator->addTag('container.service_locator');

        $definition = new Definition(GacelaBootstrapper::class);
        $definition->setArguments([
            $config['app_root_dir'],
            [
                'cache_dir' => $config['cache_dir'],
                'file_cache' => $config['file_cache'],
                'project_namespaces' => $config['project_namespaces'],
            ],
            $locator,
            $config['external_services'],
        ]);
        // The kernel fetches it from the bundle's boot(), which is not
        // dependency injection and therefore needs it public.
        $definition->setPublic(true);

        $container->setDefinition(self::BOOTSTRAPPER_ID, $definition);
    }

    private function registerCacheWarmer(ContainerBuilder $container): void
    {
        $definition = new Definition(GacelaCacheWarmer::class);
        $definition->addTag('kernel.cache_warmer');

        $container->setDefinition(self::CACHE_WARMER_ID, $definition);
    }

    /**
     * @param GacelaBundleConfig $config
     */
    private function registerCommands(ContainerBuilder $container, array $config): void
    {
        foreach (GacelaCommands::names() as $class => $name) {
            $definition = new Definition($class);
            if ($class === InitCommand::class) {
                $definition->setArguments([$config['app_root_dir']]);
            }

            // `make:module` would otherwise collide with MakerBundle, which
            // owns the whole `make:*` namespace in a Symfony application.
            $prefixed = $config['command_prefix'] . $name;
            $definition->addMethodCall('setName', [$prefixed]);
            $definition->addTag('console.command', ['command' => $prefixed]);

            $container->setDefinition(sprintf('gacela.command.%s', $name), $definition);
        }
    }

    /**
     * @param array<string, string> $externalServices
     *
     * @return array<string, Reference>
     */
    private function serviceReferences(array $externalServices): array
    {
        $references = [];

        foreach ($externalServices as $serviceId) {
            $references[$serviceId] = new Reference($serviceId);
        }

        return $references;
    }
}
