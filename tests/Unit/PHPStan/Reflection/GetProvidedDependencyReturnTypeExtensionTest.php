<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection;

use Gacela\Framework\AbstractFactory;
use Gacela\PHPStan\Reflection\GetProvidedDependencyReturnTypeExtension;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\MappedFacade;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPUnit\Framework\TestCase;

final class GetProvidedDependencyReturnTypeExtensionTest extends TestCase
{
    private GetProvidedDependencyReturnTypeExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new GetProvidedDependencyReturnTypeExtension();
    }

    public function test_applies_to_the_factory_base_class(): void
    {
        self::assertSame(AbstractFactory::class, $this->extension->getClass());
    }

    public function test_supports_only_get_provided_dependency(): void
    {
        self::assertTrue($this->extension->isMethodSupported($this->method('getProvidedDependency')));
        self::assertFalse($this->extension->isMethodSupported($this->method('make')));
        self::assertFalse($this->extension->isMethodSupported($this->method('singleton')));
    }

    public function test_a_class_string_key_resolves_to_that_class(): void
    {
        $type = $this->typeFor(new ConstantStringType(MappedFacade::class), MappedFacade::class);

        self::assertInstanceOf(ObjectType::class, $type);
        self::assertSame(MappedFacade::class, $type->getClassName());
    }

    public function test_an_interface_key_resolves_to_that_interface(): void
    {
        $type = $this->typeFor(new ConstantStringType(Scope::class), Scope::class);

        self::assertInstanceOf(ObjectType::class, $type);
        self::assertSame(Scope::class, $type->getClassName());
    }

    /**
     * A plain service id resolves to whatever the Provider registered under it,
     * which the type system cannot know. Inventing a type would be worse than
     * mixed: it is a guess the analyser then trusts.
     */
    public function test_a_plain_string_key_is_left_alone(): void
    {
        self::assertNull($this->typeFor(new ConstantStringType('some.service'), 'some.service'));
    }

    public function test_a_class_string_that_does_not_exist_is_left_alone(): void
    {
        self::assertNull($this->typeFor(new ConstantStringType('Not\\A\\Real\\Class'), 'Not\\A\\Real\\Class'));
    }

    public function test_a_non_constant_key_is_left_alone(): void
    {
        self::assertNull($this->typeFor(new MixedType(), 'whatever'));
    }

    public function test_a_call_without_exactly_one_argument_is_left_alone(): void
    {
        $scope = $this->createStub(Scope::class);
        $scope->method('getType')->willReturn(new ConstantStringType(MappedFacade::class));

        $noArgs = new MethodCall(new Variable('this'), new Identifier('getProvidedDependency'), []);
        $twoArgs = new MethodCall(new Variable('this'), new Identifier('getProvidedDependency'), [
            new Arg(new String_(MappedFacade::class)),
            new Arg(new String_('extra')),
        ]);

        self::assertNull($this->extension->getTypeFromMethodCall($this->method('getProvidedDependency'), $noArgs, $scope));
        self::assertNull($this->extension->getTypeFromMethodCall($this->method('getProvidedDependency'), $twoArgs, $scope));
    }

    private function typeFor(Type $argumentType, string $rawValue): ?Type
    {
        $scope = $this->createStub(Scope::class);
        $scope->method('getType')->willReturn($argumentType);

        $methodCall = new MethodCall(
            new Variable('this'),
            new Identifier('getProvidedDependency'),
            [new Arg(new String_($rawValue))],
        );

        return $this->extension->getTypeFromMethodCall($this->method('getProvidedDependency'), $methodCall, $scope);
    }

    private function method(string $name): MethodReflection
    {
        $method = $this->createStub(MethodReflection::class);
        $method->method('getName')->willReturn($name);

        return $method;
    }
}
