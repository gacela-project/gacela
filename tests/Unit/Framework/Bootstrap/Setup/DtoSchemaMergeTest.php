<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Bootstrap\Setup;

use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Dto\Schema\DtoType;
use Gacela\Framework\Dto\Schema\MalformedDtoSchemaException;
use PHPUnit\Framework\TestCase;

/**
 * The rule that makes a packaged shape extensible without forking its file.
 */
final class DtoSchemaMergeTest extends TestCase
{
    public function test_a_second_declarer_adds_a_property_to_the_same_class(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['reference' => DtoType::string()->required()]]);

        $setup->mergeDtoSchema(['App\Order' => ['giftMessage' => DtoType::string()]]);

        self::assertSame(
            ['reference', 'giftMessage'],
            array_keys($setup->getDtoSchema()['App\Order']),
        );
    }

    public function test_redeclaring_a_property_identically_is_idempotent(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['reference' => DtoType::string()->required()]]);

        $setup->mergeDtoSchema(['App\Order' => ['reference' => DtoType::string()->required()]]);

        self::assertSame(['reference'], array_keys($setup->getDtoSchema()['App\Order']));
    }

    /**
     * The description is prose about the property, not part of its shape:
     * refusing a redeclaration over a reworded sentence would make the merge
     * rule about wording.
     */
    public function test_a_reworded_description_is_not_a_conflict(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['total' => DtoType::int()->required()->describe('in cents')]]);

        $setup->mergeDtoSchema(['App\Order' => ['total' => DtoType::int()->required()->describe('cents, net')]]);

        self::assertSame(['total'], array_keys($setup->getDtoSchema()['App\Order']));
    }

    /**
     * The module that declared it first reads the same generated class, so
     * changing the property under it would break code that already compiles.
     */
    public function test_redeclaring_a_property_differently_is_refused(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['total' => DtoType::int()->required()]]);

        $this->expectException(MalformedDtoSchemaException::class);
        $this->expectExceptionMessage('never redefined');

        $setup->mergeDtoSchema(['App\Order' => ['total' => DtoType::string()->required()]]);
    }

    public function test_making_an_existing_property_optional_is_refused_too(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['total' => DtoType::int()->required()]]);

        $this->expectException(MalformedDtoSchemaException::class);

        $setup->mergeDtoSchema(['App\Order' => ['total' => DtoType::int()]]);
    }

    public function test_a_new_class_is_added_whole(): void
    {
        $setup = new SetupGacela();
        $setup->setDtoSchema(['App\Order' => ['reference' => DtoType::string()]]);

        $setup->mergeDtoSchema(['App\Invoice' => ['number' => DtoType::string()]]);

        self::assertSame(['App\Order', 'App\Invoice'], array_keys($setup->getDtoSchema()));
    }
}
