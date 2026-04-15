<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\SymfonyBridge\GacelaInjectCompilerPass;
use GacelaTest\SymfonyBridge\Fixtures\ConcreteBar;
use GacelaTest\SymfonyBridge\Fixtures\FooInterface;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithInject;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithoutInject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class GacelaInjectCompilerPassTest extends TestCase
{
    private GacelaInjectCompilerPass $pass;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->pass = new GacelaInjectCompilerPass();
        $this->container = new ContainerBuilder();
    }

    public function test_inject_without_override_routes_to_gacela_using_declared_type(): void
    {
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        $argument = $this->argumentFor('app.service', '$foo');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame(FooInterface::class, $argument->getClass());
        self::assertSame([FooInterface::class], $argument->getArguments());
    }

    public function test_inject_with_implementation_override_routes_to_concrete(): void
    {
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        $argument = $this->argumentFor('app.service', '$bar');
        self::assertInstanceOf(Definition::class, $argument);
        self::assertSame(ConcreteBar::class, $argument->getClass());
        self::assertSame([ConcreteBar::class], $argument->getArguments());
    }

    public function test_gacela_resolution_argument_uses_gacela_container_factory(): void
    {
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        $argument = $this->argumentFor('app.service', '$foo');
        self::assertInstanceOf(Definition::class, $argument);

        $factory = $argument->getFactory();
        self::assertIsArray($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('gacela.container', (string) $factory[0]);
        self::assertSame('get', $factory[1]);
    }

    public function test_service_without_inject_is_left_untouched(): void
    {
        $definition = $this->container->register('app.plain', ServiceWithoutInject::class);

        $this->pass->process($this->container);

        self::assertSame([], $definition->getArguments());
    }

    public function test_conflict_with_existing_named_argument_throws(): void
    {
        $this->container
            ->register('app.service', ServiceWithInject::class)
            ->setArgument('$foo', 'already-set-by-symfony');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('app.service');
        $this->expectExceptionMessage('$foo');

        $this->pass->process($this->container);
    }

    public function test_conflict_with_existing_positional_argument_throws(): void
    {
        $this->container
            ->register('app.service', ServiceWithInject::class)
            ->setArgument(0, 'already-set-by-symfony');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('$foo');

        $this->pass->process($this->container);
    }

    public function test_abstract_definition_is_skipped(): void
    {
        $this->container
            ->register('app.abstract', ServiceWithInject::class)
            ->setAbstract(true);

        $this->pass->process($this->container);

        // Still no arguments set — abstract definitions are not rewritten.
        self::assertSame([], $this->container->getDefinition('app.abstract')->getArguments());
    }

    public function test_synthetic_definition_is_skipped(): void
    {
        $this->container
            ->register('app.synthetic')
            ->setSynthetic(true);

        // Should not throw even though synthetic definitions have no class.
        $this->pass->process($this->container);

        self::assertTrue($this->container->getDefinition('app.synthetic')->isSynthetic());
    }

    public function test_custom_gacela_service_id_is_honored(): void
    {
        $pass = new GacelaInjectCompilerPass('custom.gacela.container');
        $this->container->register('app.service', ServiceWithInject::class);

        $pass->process($this->container);

        $argument = $this->argumentFor('app.service', '$foo');
        self::assertInstanceOf(Definition::class, $argument);
        /** @var array{0: Reference, 1: string} $factory */
        $factory = $argument->getFactory();
        self::assertSame('custom.gacela.container', (string) $factory[0]);
    }

    private function argumentFor(string $serviceId, string $namedKey): mixed
    {
        return $this->container->getDefinition($serviceId)->getArgument($namedKey);
    }
}
