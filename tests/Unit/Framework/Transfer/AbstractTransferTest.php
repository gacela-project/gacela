<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Transfer;

use Gacela\Framework\Transfer\UnknownPropertyException;
use GacelaTest\Unit\Framework\Transfer\Fixtures\PersonTransfer;
use PHPUnit\Framework\TestCase;

final class AbstractTransferTest extends TestCase
{
    public function test_fluent_setters(): void
    {
        $person = (new PersonTransfer())
            ->setAge(10)
            ->setName('gacela');

        self::assertSame(10, $person->age);
        self::assertSame('gacela', $person->name);
    }

    public function test_getters(): void
    {
        $person = new PersonTransfer();
        $person->age = 10;
        $person->name = 'gacela';

        self::assertSame(10, $person->getAge());
        self::assertSame('gacela', $person->getName());
    }

    public function test_from_array(): void
    {
        $person = (new PersonTransfer())
            ->fromArray(['age' => 10, 'name' => 'gacela']);

        self::assertSame(10, $person->age);
        self::assertSame('gacela', $person->name);
    }

    public function test_to_array(): void
    {
        $person = new PersonTransfer();
        $person->age = 10;
        $person->name = 'gacela';

        self::assertSame(
            ['id' => null, 'name' => 'gacela', 'age' => 10],
            $person->toArray()
        );
    }

    public function test_set_wrong_type_from_setter(): void
    {
        $this->expectErrorMessageMatches('/.*PersonTransfer.*age.*/');

        (new PersonTransfer())->setAge(10.5);
    }

    public function test_set_wrong_type_from_array(): void
    {
        $this->expectErrorMessageMatches('/.*PersonTransfer.*age.*/');

        (new PersonTransfer())->fromArray(['age' => 10.5]);
    }

    public function test_set_nonexistent_property_from_array(): void
    {
        $person = (new PersonTransfer())->fromArray(['non-existent' => 123]);

        self::assertInstanceOf(PersonTransfer::class, $person);
    }

    public function test_set_nonexistent_property_using_public_property(): void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Unknown property with name: nonExistentProperty');

        $person = new PersonTransfer();
        $person->nonExistentProperty = 123;
    }

    public function test_set_nonexistent_property_using_setter(): void
    {
        $this->expectException(UnknownPropertyException::class);
        $this->expectExceptionMessage('Unknown property with name: nonExistentProperty');

        (new PersonTransfer())->setNonExistentProperty(123);
    }
}
