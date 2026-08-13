<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge;

use Gacela\Framework\Gacela;
use Gacela\LaravelBridge\GacelaInjectListener;
use GacelaTest\LaravelBridge\Fixtures\BareConstructorConsumer;
use GacelaTest\LaravelBridge\Fixtures\BridgeAttributePropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\ConstructorConsumer;
use GacelaTest\LaravelBridge\Fixtures\ContractPropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\ContractSetterConsumer;
use GacelaTest\LaravelBridge\Fixtures\CountingService;
use GacelaTest\LaravelBridge\Fixtures\InheritedPropertiesConsumer;
use GacelaTest\LaravelBridge\Fixtures\InitializedPropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\PrivateSetterConsumer;
use GacelaTest\LaravelBridge\Fixtures\PropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\ReadonlyPropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\SetterConsumer;
use GacelaTest\LaravelBridge\Fixtures\TestApplication;
use GacelaTest\LaravelBridge\Fixtures\TwoParameterSetterConsumer;
use GacelaTest\LaravelBridge\Fixtures\UntypedPropertyConsumer;
use GacelaTest\LaravelBridge\Fixtures\UntypedSetterConsumer;
use Illuminate\Contracts\Container\BindingResolutionException;
use LogicException;
use ReflectionProperty;

/**
 * `#[Inject]` on classes *Laravel* builds, driven through a real Illuminate
 * container: the attribute rides Laravel's own contextual-attribute mechanism
 * and the listener rides `afterResolving`, and neither exists outside a real
 * resolution.
 */
final class GacelaInjectTest extends LaravelBridgeTestCase
{
    private TestApplication $app;

    protected function setUp(): void
    {
        $this->app = new TestApplication([
            'external_services' => [CountingService::class => 'app.counting'],
        ]);
        $this->app->singleton('app.counting', static fn (): CountingService => new CountingService(CountingService::FROM_LARAVEL));
        $this->app->boot();
    }

    public function test_a_constructor_parameter_resolves_through_gacela(): void
    {
        $consumer = $this->app->make(ConstructorConsumer::class);

        self::assertSame(CountingService::FROM_LARAVEL, $consumer->service->name());
        self::assertSame($this->app->make('app.counting'), $consumer->service);
    }

    /**
     * Laravel hands a contextual attribute no parameter to read a type from,
     * so the bare form cannot work on a constructor -- and it must say so,
     * not silently autowire something else.
     */
    public function test_a_bare_constructor_parameter_is_refused_with_directions(): void
    {
        $this->expectException(BindingResolutionException::class);
        // The whole message in order: the refusal, the example, the way out.
        $this->expectExceptionMessageMatches('/explicit class.*ProductFacade::class.*bare form works/s');

        $this->app->make(BareConstructorConsumer::class);
    }

    /**
     * The Gacela-namespace attribute, on a private property: the listener must
     * honor either namespace, and the type is on the member, so the bare form
     * works here.
     */
    public function test_a_property_is_injected_after_laravel_builds_the_instance(): void
    {
        $consumer = $this->app->make(PropertyConsumer::class);

        self::assertSame(CountingService::FROM_LARAVEL, $consumer->service()->name());
    }

    /**
     * "Honouring the attribute under either namespace" is a README promise, and
     * only the Gacela one was exercised on a property. The bridge's is the
     * import a reader already has from the constructor example above it.
     */
    public function test_a_property_carrying_the_bridge_attribute_is_injected_too(): void
    {
        $consumer = $this->app->make(BridgeAttributePropertyConsumer::class);

        self::assertSame(CountingService::FROM_LARAVEL, $consumer->service()->name());
    }

    public function test_a_setter_is_called_with_what_gacela_resolves(): void
    {
        $consumer = $this->app->make(SetterConsumer::class);

        self::assertSame(CountingService::FROM_LARAVEL, $consumer->service()?->name());
    }

    /**
     * A readonly property cannot be written after construction; pretending
     * otherwise would fail deep in reflection. Refusing it by name is the
     * kindness.
     */
    public function test_a_readonly_property_is_refused_by_name(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('readonly');

        $this->app->make(ReadonlyPropertyConsumer::class);
    }

    /**
     * Injection fills what construction left unset; a property that already
     * holds a value -- even null -- keeps it.
     */
    public function test_an_initialized_property_keeps_its_value(): void
    {
        $consumer = $this->app->make(InitializedPropertyConsumer::class);

        self::assertNull($consumer->service());
    }

    public function test_a_private_setter_is_refused_by_name(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('public and non-static');

        $this->app->make(PrivateSetterConsumer::class);
    }

    public function test_an_untyped_property_without_an_explicit_class_is_refused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('class type');

        $this->app->make(UntypedPropertyConsumer::class);
    }

    public function test_an_untyped_setter_parameter_is_refused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('class type');

        $this->app->make(UntypedSetterConsumer::class);
    }

    /**
     * The parameter names the contract, the attribute names the
     * implementation, and only the implementation is bound: resolving the
     * parameter type instead would fail loudly.
     */
    public function test_a_setter_honors_an_explicit_implementation_over_the_parameter_type(): void
    {
        $consumer = $this->app->make(ContractSetterConsumer::class);

        self::assertInstanceOf(CountingService::class, $consumer->service());
    }

    /**
     * With two parameters there is no saying which one the override was meant
     * for: refused, not guessed at -- silently resolving both by type would be
     * the one quiet path in a class that otherwise fails loudly.
     */
    public function test_an_explicit_implementation_over_two_parameters_is_refused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('exactly one parameter');

        $this->app->make(TwoParameterSetterConsumer::class);
    }

    public function test_a_property_honors_an_explicit_implementation_over_its_type(): void
    {
        $consumer = $this->app->make(ContractPropertyConsumer::class);

        self::assertInstanceOf(CountingService::class, $consumer->service());
    }

    /**
     * Two `#[Inject]` properties, one private on the parent, invisible to the
     * child's reflection: both must arrive.
     */
    public function test_a_parents_private_property_is_injected_alongside_the_childs(): void
    {
        $consumer = $this->app->make(InheritedPropertiesConsumer::class);

        self::assertSame(CountingService::FROM_LARAVEL, $consumer->baseService()->name());
        self::assertSame(CountingService::FROM_LARAVEL, $consumer->ownService()->name());
    }

    /**
     * White-box on purpose: a class with no `#[Inject]` member must be
     * remembered as `null`, the fast path every later resolution of it takes.
     * An empty plan would behave the same today and quietly cost the loop
     * set-up on every resolution forever.
     */
    public function test_a_class_with_nothing_to_inject_is_remembered_as_null(): void
    {
        // Evict the memo first: under random order another test may have
        // planned this class already, and a memoized answer would let this
        // test pass without ever exercising the planning itself.
        $property = new ReflectionProperty(GacelaInjectListener::class, 'plans');
        /** @var array<class-string, mixed> $plans */
        $plans = $property->getValue();
        unset($plans[CountingService::class]);
        $property->setValue(null, $plans);

        $this->app->make(CountingService::class);

        /** @var array<class-string, mixed> $plans */
        $plans = $property->getValue();

        self::assertArrayHasKey(CountingService::class, $plans);
        self::assertNull($plans[CountingService::class]);
    }
}
