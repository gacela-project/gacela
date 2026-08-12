<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Dto\Schema;

use Gacela\Framework\Dto\Schema\DtoSchema;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Dto\Schema\MalformedDtoSchemaException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DtoSchemaTest extends TestCase
{
    public function test_declaring_nothing_is_empty(): void
    {
        self::assertTrue(DtoSchema::empty()->isEmpty());
        self::assertSame([], DtoSchema::empty()->shapes());
    }

    public function test_a_declared_shape_is_reported_by_its_class_name(): void
    {
        $schema = DtoSchema::fromArray([
            'App\Checkout\Order' => ['reference' => DtoType::string()],
        ]);

        self::assertFalse($schema->isEmpty());
        self::assertTrue($schema->declares('App\Checkout\Order'));
        self::assertFalse($schema->declares('App\Checkout\Other'));
    }

    /**
     * The id is the class the shape generates, so a bare label has nowhere to
     * be written and is refused where it is declared.
     */
    public function test_a_shape_id_that_is_not_a_class_name_is_refused(): void
    {
        $this->expectException(MalformedDtoSchemaException::class);
        $this->expectExceptionMessage('Checkout.Order');

        DtoSchema::fromArray(['Checkout.Order' => ['reference' => DtoType::string()]]);
    }

    public function test_a_property_that_is_not_a_type_is_refused(): void
    {
        $this->expectException(MalformedDtoSchemaException::class);
        $this->expectExceptionMessage('reference');

        DtoSchema::fromArray(['App\Checkout\Order' => ['reference' => 'string']]);
    }

    public function test_a_property_name_php_could_not_carry_is_refused(): void
    {
        $this->expectException(MalformedDtoSchemaException::class);

        DtoSchema::fromArray(['App\Checkout\Order' => ['not a name' => DtoType::string()]]);
    }

    /**
     * A default is what makes an absent value legitimate, so requiring it as
     * well is two answers to one question.
     */
    public function test_a_property_both_required_and_defaulted_is_refused(): void
    {
        $this->expectException(MalformedDtoSchemaException::class);
        $this->expectExceptionMessage('two answers');

        DtoSchema::fromArray([
            'App\Checkout\Order' => ['total' => DtoType::int()->required()->default(0)],
        ]);
    }

    public function test_a_default_of_the_wrong_type_is_refused(): void
    {
        $this->expectException(MalformedDtoSchemaException::class);
        $this->expectExceptionMessage('total');

        DtoSchema::fromArray([
            'App\Checkout\Order' => ['total' => DtoType::int()->default('free')],
        ]);
    }

    /**
     * `0` is about the value, not about php's literal syntax.
     */
    public function test_an_int_is_a_legitimate_float_default(): void
    {
        $schema = DtoSchema::fromArray([
            'App\Checkout\Order' => ['rate' => DtoType::float()->default(0)],
        ]);

        self::assertTrue($schema->declares('App\Checkout\Order'));
    }

    public function test_a_default_of_each_declared_type_is_accepted(): void
    {
        $schema = DtoSchema::fromArray([
            'App\Checkout\Order' => [
                'reference' => DtoType::string()->default('none'),
                'total' => DtoType::int()->default(0),
                'rate' => DtoType::float()->default(1.5),
                'paid' => DtoType::bool()->default(false),
                'lines' => DtoType::array()->default([]),
            ],
        ]);

        self::assertCount(5, $schema->shapes()['App\Checkout\Order']);
    }

    /**
     * @param array<string, DtoType> $properties
     */
    #[DataProvider('mismatchedDefaultProvider')]
    public function test_a_default_of_the_wrong_type_is_refused_for_every_type(array $properties): void
    {
        $this->expectException(MalformedDtoSchemaException::class);

        DtoSchema::fromArray(['App\Checkout\Order' => $properties]);
    }

    /**
     * @return iterable<string, array{array<string, DtoType>}>
     */
    public static function mismatchedDefaultProvider(): iterable
    {
        yield 'string' => [['a' => DtoType::string()->default(1)]];
        yield 'int' => [['a' => DtoType::int()->default('1')]];
        yield 'float' => [['a' => DtoType::float()->default('1.5')]];
        yield 'bool' => [['a' => DtoType::bool()->default(1)]];
        yield 'array' => [['a' => DtoType::array()->default('[]')]];
    }

    /**
     * Two declarers produce the same class whichever config source loads first,
     * so the generated file cannot depend on declaration order.
     */
    public function test_shapes_and_properties_come_out_sorted(): void
    {
        $schema = DtoSchema::fromArray([
            'App\Zeta' => ['beta' => DtoType::string(), 'alpha' => DtoType::string()],
            'App\Alpha' => ['gamma' => DtoType::string()],
        ]);

        self::assertSame(['App\Alpha', 'App\Zeta'], array_keys($schema->shapes()));
        self::assertSame(['alpha', 'beta'], array_keys($schema->shapes()['App\Zeta']));
    }
}
