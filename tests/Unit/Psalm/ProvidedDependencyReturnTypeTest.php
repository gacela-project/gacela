<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Psalm;

use Gacela\Framework\AbstractFactory;
use Gacela\Psalm\ProvidedDependencyReturnType;
use GacelaTest\Unit\Psalm\Fixture\ProvidedClock;
use GacelaTest\Unit\Psalm\Fixture\ProvidedContract;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use Psalm\Config;
use Psalm\NodeTypeProvider;
use Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Psalm\StatementsSource;
use Psalm\Type\Atomic\TLiteralString;
use Psalm\Type\Atomic\TString;
use Psalm\Type\Union;
use ReflectionClass;
use ReflectionProperty;

/**
 * Drives the Psalm hook directly, in-process.
 *
 * `ProvidedDependencyPluginTest` runs the same thing through a real
 * `vendor/bin/psalm`, which is the stronger proof -- but it is a subprocess, so
 * coverage cannot see it and every mutant here would count as untested.
 */
final class ProvidedDependencyReturnTypeTest extends TestCase
{
    private ?Config $previousConfig = null;

    /**
     * Building any Psalm type reads the global config for its string-length
     * limit, and a unit test has no analysis to have initialised one. The
     * defaults on the class are all this needs.
     */
    protected function setUp(): void
    {
        $instance = new ReflectionProperty(Config::class, 'instance');
        $this->previousConfig = $instance->getValue();
        $instance->setValue(null, (new ReflectionClass(Config::class))->newInstanceWithoutConstructor());
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Config::class, 'instance'))->setValue(null, $this->previousConfig);
    }

    public function test_it_applies_to_the_factory_base_class(): void
    {
        self::assertSame([AbstractFactory::class], ProvidedDependencyReturnType::getClassLikeNames());
    }

    public function test_a_class_string_key_is_typed_as_that_class(): void
    {
        $type = $this->returnTypeFor($this->literal(ProvidedClock::class));

        self::assertSame(ProvidedClock::class, (string)$type);
    }

    /**
     * An interface is as resolvable as a class, and is the more common thing for
     * a Provider to hand out.
     */
    public function test_an_interface_key_is_typed_too(): void
    {
        $type = $this->returnTypeFor($this->literal(ProvidedContract::class));

        self::assertSame(ProvidedContract::class, (string)$type);
    }

    /**
     * Nothing in the type system says what `'some.service'` resolves to, and a
     * guess would be worse than mixed: mixed is honestly unknown, a guess is
     * confidently wrong and then trusted.
     */
    public function test_a_plain_string_key_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor($this->literal('some.service')));
    }

    public function test_a_name_that_resolves_to_nothing_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor($this->literal('App\Nope\NotAClass')));
    }

    /**
     * The PHPStan extension walks every constant string it is given and stops at
     * the first that names something, so a union has to answer the same here --
     * sampling only the first would depend on which branch Psalm happened to
     * order first.
     */
    public function test_a_union_is_scanned_past_a_key_that_names_nothing(): void
    {
        $type = $this->returnTypeFor(new Union([
            new TLiteralString('some.service'),
            new TLiteralString(ProvidedClock::class),
        ]));

        self::assertSame(ProvidedClock::class, (string)$type);
    }

    /**
     * The key is read through the inferred type rather than the AST, so one held
     * in a variable resolves the same as one written at the call site.
     */
    public function test_a_key_that_is_not_a_literal_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor(new Union([new TString()])));
    }

    public function test_an_argument_psalm_could_not_type_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor(null));
    }

    public function test_another_method_on_the_factory_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor($this->literal(ProvidedClock::class), method: 'creatething'));
    }

    public function test_a_call_without_arguments_is_left_alone(): void
    {
        self::assertNull($this->returnTypeFor($this->literal(ProvidedClock::class), args: []));
    }

    /**
     * `getProvidedDependency()` takes one key. Two arguments is not a call this
     * hook understands, so it says nothing rather than reading the first.
     */
    public function test_a_call_with_two_arguments_is_left_alone(): void
    {
        $args = [
            new Arg($this->classConstFetch()),
            new Arg($this->classConstFetch()),
        ];

        self::assertNull($this->returnTypeFor($this->literal(ProvidedClock::class), args: $args));
    }

    /**
     * @param list<Arg>|null $args
     */
    private function returnTypeFor(
        ?Union $argumentType,
        string $method = 'getprovideddependency',
        ?array $args = null,
    ): ?Union {
        $args ??= [new Arg($this->classConstFetch())];

        $typeProvider = $this->createStub(NodeTypeProvider::class);
        $typeProvider->method('getType')->willReturn($argumentType);

        $source = $this->createStub(StatementsSource::class);
        $source->method('getNodeTypeProvider')->willReturn($typeProvider);

        return ProvidedDependencyReturnType::getMethodReturnType(
            $this->event($method, $args, $source),
        );
    }

    /**
     * The event is a value object; the hook reads three of its nine fields, and
     * the constructor also demands a `Codebase`, which is final and wants a whole
     * analysis context. Bypassing it keeps the test about the hook.
     *
     * @param list<Arg> $args
     */
    private function event(string $method, array $args, StatementsSource $source): MethodReturnTypeProviderEvent
    {
        $reflection = new ReflectionClass(MethodReturnTypeProviderEvent::class);
        $event = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('method_name_lowercase')->setValue($event, $method);
        $reflection->getProperty('source')->setValue($event, $source);
        // getCallArgs() reads them off the call node rather than storing them.
        $reflection->getProperty('stmt')->setValue(
            $event,
            new MethodCall(new Variable('this'), new Identifier('getProvidedDependency'), $args),
        );

        return $event;
    }

    private function literal(string $value): Union
    {
        return new Union([new TLiteralString($value)]);
    }

    private function classConstFetch(): \PhpParser\Node\Expr\ClassConstFetch
    {
        return new ClassConstFetch(new Name('Whatever'), new Identifier('class'));
    }
}
