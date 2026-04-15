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

    public function test_inject_rewrites_arguments_to_gacela_factory_definitions(): void
    {
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        // Plain #[Inject] → routes the declared interface through Gacela.
        $foo = $this->argumentFor('app.service', '$foo');
        self::assertInstanceOf(Definition::class, $foo);
        self::assertSame(FooInterface::class, $foo->getClass());
        self::assertSame([FooInterface::class], $foo->getArguments());
        self::assertFactoryRoutesTo($foo, 'gacela.container');

        // #[Inject(Concrete::class)] → routes the override instead.
        $bar = $this->argumentFor('app.service', '$bar');
        self::assertInstanceOf(Definition::class, $bar);
        self::assertSame(ConcreteBar::class, $bar->getClass());
        self::assertSame([ConcreteBar::class], $bar->getArguments());
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
        self::assertFactoryRoutesTo($argument, 'custom.gacela.container');
    }

    private function argumentFor(string $serviceId, string $namedKey): mixed
    {
        return $this->container->getDefinition($serviceId)->getArgument($namedKey);
    }

    private static function assertFactoryRoutesTo(Definition $argument, string $expectedServiceId): void
    {
        /** @var array{0: Reference, 1: string} $factory */
        $factory = $argument->getFactory();
        self::assertSame($expectedServiceId, (string) $factory[0]);
        self::assertSame('get', $factory[1]);
    }
}
