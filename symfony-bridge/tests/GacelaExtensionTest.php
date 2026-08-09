<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\Console\Infrastructure\Command\InitCommand;
use Gacela\Console\Infrastructure\Command\MakeModuleCommand;
use Gacela\SymfonyBridge\DependencyInjection\GacelaExtension;
use Gacela\SymfonyBridge\GacelaBootstrapper;
use Gacela\SymfonyBridge\GacelaCacheWarmer;
use Gacela\SymfonyBridge\GacelaCommands;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use function count;

/**
 * What the extension puts in the container, read before compilation removes
 * whatever nothing references.
 */
final class GacelaExtensionTest extends TestCase
{
    public function test_the_bootstrapper_is_registered_public_because_boot_fetches_it(): void
    {
        $container = $this->load();

        $definition = $container->getDefinition(GacelaExtension::BOOTSTRAPPER_ID);

        self::assertSame(GacelaBootstrapper::class, $definition->getClass());
        self::assertTrue($definition->isPublic(), 'the bundle fetches it from boot(), which is not injection');
    }

    public function test_the_cache_warmer_is_registered_with_the_tag_symfony_looks_for(): void
    {
        $definition = $this->load()->getDefinition(GacelaExtension::CACHE_WARMER_ID);

        self::assertSame(GacelaCacheWarmer::class, $definition->getClass());
        self::assertArrayHasKey('kernel.cache_warmer', $definition->getTags());
    }

    public function test_every_gacela_command_is_registered_under_the_prefix(): void
    {
        $container = $this->load();

        foreach (GacelaCommands::names() as $name) {
            $definition = $container->getDefinition('gacela.command.' . $name);

            self::assertSame([['setName', ['gacela:' . $name]]], $definition->getMethodCalls());
            self::assertSame(
                [['command' => 'gacela:' . $name]],
                $definition->getTag('console.command'),
            );
        }

        self::assertGreaterThan(10, count(GacelaCommands::names()), 'precondition: there are commands to register');
    }

    /**
     * MakerBundle owns the whole `make:*` namespace in a Symfony application,
     * so an unprefixed `make:module` would collide with it.
     */
    public function test_the_prefix_is_what_keeps_make_module_from_colliding_with_makerbundle(): void
    {
        $names = GacelaCommands::names();

        self::assertSame('make:module', $names[MakeModuleCommand::class]);
        self::assertSame(
            [['setName', ['gacela:make:module']]],
            $this->load()->getDefinition('gacela.command.make:module')->getMethodCalls(),
        );
    }

    public function test_the_prefix_can_be_changed(): void
    {
        $container = $this->load([['command_prefix' => 'g:']]);

        self::assertSame(
            [['setName', ['g:make:module']]],
            $container->getDefinition('gacela.command.make:module')->getMethodCalls(),
        );
    }

    public function test_the_init_command_is_told_where_the_project_is(): void
    {
        $definition = $this->load([['app_root_dir' => '/app']])->getDefinition('gacela.command.init');

        self::assertSame(InitCommand::class, $definition->getClass());
        self::assertSame(['/app'], $definition->getArguments());
    }

    public function test_commands_can_be_left_out(): void
    {
        $container = $this->load([['register_commands' => false]]);

        self::assertFalse($container->hasDefinition('gacela.command.make:module'));
        self::assertTrue($container->hasDefinition(GacelaExtension::BOOTSTRAPPER_ID));
    }

    public function test_a_disabled_bundle_registers_nothing_at_all(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->hasDefinition(GacelaExtension::BOOTSTRAPPER_ID));
        self::assertFalse($container->hasDefinition(GacelaExtension::CACHE_WARMER_ID));
        self::assertFalse($container->hasDefinition('gacela.command.make:module'));
    }

    public function test_a_listed_external_service_is_reached_through_a_locator(): void
    {
        $container = $this->load([['external_services' => ['logger' => 'monolog.logger']]]);

        $arguments = $container->getDefinition(GacelaExtension::BOOTSTRAPPER_ID)->getArguments();

        self::assertSame(['logger' => 'monolog.logger'], $arguments[3]);
        self::assertInstanceOf(Definition::class, $arguments[2]);
        self::assertArrayHasKey('container.service_locator', $arguments[2]->getTags());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs = [[]]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new GacelaExtension())->load($configs, $container);

        return $container;
    }
}
