<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\SymfonyBridge\GacelaInjectCompilerPass;
use GacelaTest\SymfonyBridge\Fixtures\ConcreteBar;
use GacelaTest\SymfonyBridge\Fixtures\FooInterface;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithBuiltinTypedInject;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithInject;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithoutInject;
use GacelaTest\SymfonyBridge\Fixtures\ServiceWithSubclassedInject;
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
        $this->assertFactoryRoutesTo($foo, 'gacela.container');

        // #[Inject(Concrete::class)] → routes the override instead.
        $bar = $this->argumentFor('app.service', '$bar');
        self::assertInstanceOf(Definition::class, $bar);
        self::assertSame(ConcreteBar::class, $bar->getClass());
        self::assertSame([ConcreteBar::class], $bar->getArguments());
    }

    /**
     * The container's attribute is deliberately not `final`, so a consumer can
     * re-present it under its own namespace, and `Gacela\Framework\Attribute\Inject`
     * does. Reading for an exact FQN honours neither, and the failure is silent:
     * the argument is simply never rewritten and Symfony autowires it instead.
     */
    public function test_a_subclass_of_the_inject_attribute_is_honored(): void
    {
        $this->container->register('app.subclassed', ServiceWithSubclassedInject::class);

        $this->pass->process($this->container);

        $foo = $this->argumentFor('app.subclassed', '$foo');
        self::assertInstanceOf(Definition::class, $foo);
        self::assertSame(FooInterface::class, $foo->getClass());
        $this->assertFactoryRoutesTo($foo, 'gacela.container');

        $bar = $this->argumentFor('app.subclassed', '$bar');
        self::assertInstanceOf(Definition::class, $bar);
        self::assertSame(ConcreteBar::class, $bar->getClass());
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
        // Asserted whole rather than by fragment: the message names the service
        // and the parameter so a build failure points at the line to change, and
        // half of it going missing still contains every fragment worth grepping.
        $this->expectExceptionMessage(
            'Gacela #[Inject] conflicts with an existing Symfony argument on service "app.service" '
            . 'parameter "$foo". Remove the Symfony argument or drop the #[Inject] attribute.',
        );

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

    /**
     * Skipping an abstract definition must not stop the scan. The definitions
     * are walked in registration order, so an abstract one registered first
     * would hide every service after it.
     */
    public function test_an_abstract_definition_does_not_stop_later_ones_being_rewritten(): void
    {
        $this->container
            ->register('app.abstract', ServiceWithInject::class)
            ->setAbstract(true);
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        self::assertSame([], $this->container->getDefinition('app.abstract')->getArguments());
        self::assertInstanceOf(Definition::class, $this->argumentFor('app.service', '$foo'));
    }

    /**
     * The generated factory definition is an implementation detail of the
     * argument it fills, so it has no business being reachable from the
     * container by id.
     */
    public function test_the_generated_argument_definition_is_not_public(): void
    {
        $this->container->register('app.service', ServiceWithInject::class);

        $this->pass->process($this->container);

        $foo = $this->argumentFor('app.service', '$foo');
        self::assertInstanceOf(Definition::class, $foo);
        self::assertFalse($foo->isPublic());
    }

    /**
     * `#[Inject]` with no override on a builtin-typed parameter names no class,
     * so there is nothing for Gacela to resolve and the argument is left for
     * Symfony. Rewriting it would ask the container for a service called
     * "string".
     */
    public function test_a_builtin_typed_parameter_is_left_alone(): void
    {
        $this->container->register('app.builtin', ServiceWithBuiltinTypedInject::class);

        $this->pass->process($this->container);

        self::assertSame([], $this->container->getDefinition('app.builtin')->getArguments());
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
        $this->assertFactoryRoutesTo($argument, 'custom.gacela.container');
    }

    private function argumentFor(string $serviceId, string $namedKey): mixed
    {
        return $this->container->getDefinition($serviceId)->getArgument($namedKey);
    }

    private function assertFactoryRoutesTo(Definition $argument, string $expectedServiceId): void
    {
        /** @var array{0: Reference, 1: string} $factory */
        $factory = $argument->getFactory();
        self::assertSame($expectedServiceId, (string) $factory[0]);
        self::assertSame('get', $factory[1]);
    }
}
