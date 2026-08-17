<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * What `config/packages/gacela.yaml` may say.
 *
 * Validated by a tree rather than read out of an array: an unknown or
 * mistyped key fails at compile time, where it is a five-second fix, instead of
 * at the first use of whatever it was supposed to configure.
 */
final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('gacela');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Bootstrap Gacela when the kernel boots.')
                ->end()
                ->scalarNode('app_root_dir')
                    ->defaultValue('%kernel.project_dir%')
                    ->info('The directory holding gacela.php. Defaults to the Symfony project dir.')
                ->end()
                ->scalarNode('cache_dir')
                    ->defaultNull()
                    ->info("Where Gacela writes its caches. Null leaves Gacela's own default in place.")
                ->end()
                ->booleanNode('file_cache')
                    ->defaultNull()
                    ->info("Enable the on-disk resolution cache. Null leaves Gacela's own default in place.")
                ->end()
                ->arrayNode('project_namespaces')
                    ->scalarPrototype()->end()
                    ->info('Namespaces Gacela scans for modules.')
                ->end()
                ->arrayNode('external_services')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                    ->info('Gacela external-service key => Symfony service id, reachable from a Factory.')
                ->end()
                ->booleanNode('register_commands')
                    ->defaultTrue()
                    ->info("Add Gacela's console commands to bin/console.")
                ->end()
                ->scalarNode('command_prefix')
                    ->defaultValue('gacela:')
                    ->info('Prefix for those commands. Required: `make:*` would otherwise collide with MakerBundle.')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
