<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\Schema;

use Gacela\Framework\Config\Schema\ConfigSchema;
use Gacela\Framework\Config\Schema\ConfigType;
use Gacela\Framework\Config\Schema\MalformedConfigSchemaException;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTest extends TestCase
{
    public function test_an_empty_schema_declares_nothing_and_finds_nothing(): void
    {
        $schema = ConfigSchema::empty();

        self::assertTrue($schema->isEmpty());
        self::assertSame([], $schema->violations(['anything' => 'goes']));
        self::assertSame([], $schema->defaults());
    }

    public function test_a_required_key_that_no_source_provides_is_a_violation(): void
    {
        $violations = $this->schema(['db.dsn' => ConfigType::string()->required()])->violations([]);

        self::assertCount(1, $violations);
        self::assertSame('db.dsn', $violations[0]->key);
        self::assertStringContainsString('missing', $violations[0]->message);
    }

    public function test_a_required_key_that_is_provided_is_not_a_violation(): void
    {
        self::assertSame(
            [],
            $this->schema(['db.dsn' => ConfigType::string()->required()])->violations(['db.dsn' => 'sqlite::memory:']),
        );
    }

    public function test_a_value_of_the_wrong_type_is_a_violation_naming_both_types(): void
    {
        $violations = $this->schema(['retries' => ConfigType::int()->required()])->violations(['retries' => 'three']);

        self::assertCount(1, $violations);
        self::assertStringContainsString('int', $violations[0]->message);
        self::assertStringContainsString('string', $violations[0]->message);
    }

    public function test_an_optional_key_that_is_absent_is_not_a_violation(): void
    {
        self::assertSame([], $this->schema(['retries' => ConfigType::int()])->violations([]));
    }

    /**
     * An optional key is optional about being *there*, not about what it is.
     */
    public function test_an_optional_key_that_is_present_is_still_type_checked(): void
    {
        self::assertCount(1, $this->schema(['retries' => ConfigType::int()])->violations(['retries' => 'three']));
    }

    public function test_every_declared_type_accepts_its_own_values(): void
    {
        $schema = $this->schema([
            'a' => ConfigType::string(),
            'b' => ConfigType::int(),
            'c' => ConfigType::float(),
            'd' => ConfigType::bool(),
            'e' => ConfigType::array(),
        ]);

        self::assertSame([], $schema->violations([
            'a' => 'text',
            'b' => 1,
            'c' => 1.5,
            'd' => false,
            'e' => ['x'],
        ]));
    }

    /**
     * `timeout: 5` in a config file is an int, and refusing it for a float would
     * be a rule about php's literal syntax rather than about the value.
     */
    public function test_a_float_accepts_an_int(): void
    {
        self::assertSame([], $this->schema(['timeout' => ConfigType::float()])->violations(['timeout' => 5]));
    }

    public function test_an_int_does_not_accept_a_float(): void
    {
        self::assertCount(1, $this->schema(['retries' => ConfigType::int()])->violations(['retries' => 1.5]));
    }

    public function test_a_bool_does_not_accept_the_string_that_looks_like_one(): void
    {
        self::assertCount(1, $this->schema(['debug' => ConfigType::bool()])->violations(['debug' => 'true']));
    }

    public function test_every_violation_is_reported_not_only_the_first(): void
    {
        $violations = $this->schema([
            'a' => ConfigType::string()->required(),
            'b' => ConfigType::int()->required(),
        ])->violations([]);

        self::assertCount(2, $violations);
    }

    public function test_a_default_is_offered_for_a_key_no_source_provides(): void
    {
        self::assertSame(['retries' => 3], $this->schema(['retries' => ConfigType::int()->default(3)])->defaults());
    }

    public function test_a_key_without_a_default_offers_none(): void
    {
        self::assertSame([], $this->schema(['retries' => ConfigType::int()])->defaults());
    }

    /**
     * A default is what makes an absent key legitimate; requiring it as well
     * would be two answers to the same question.
     */
    public function test_a_defaulted_key_is_never_missing(): void
    {
        self::assertSame([], $this->schema(['retries' => ConfigType::int()->default(3)])->violations([]));
    }

    public function test_a_default_of_the_wrong_type_is_refused_when_the_schema_is_declared(): void
    {
        $this->expectException(MalformedConfigSchemaException::class);
        $this->expectExceptionMessage('retries');

        $this->schema(['retries' => ConfigType::int()->default('three')]);
    }

    public function test_a_required_key_cannot_also_carry_a_default(): void
    {
        $this->expectException(MalformedConfigSchemaException::class);

        $this->schema(['retries' => ConfigType::int()->required()->default(3)]);
    }

    public function test_a_declaration_that_is_not_a_type_is_refused(): void
    {
        $this->expectException(MalformedConfigSchemaException::class);
        $this->expectExceptionMessage('retries');

        /** @psalm-suppress InvalidArgument */
        ConfigSchema::fromArray(['retries' => 'int']);
    }

    public function test_it_knows_which_keys_it_declares(): void
    {
        $schema = $this->schema(['a' => ConfigType::string(), 'b' => ConfigType::int()]);

        self::assertSame(['a', 'b'], $schema->declaredKeys());
        self::assertTrue($schema->declares('a'));
        self::assertFalse($schema->declares('c'));
    }

    /**
     * The description is what a violation can say beyond "wrong type", so it
     * has to survive into the message.
     */
    public function test_a_description_is_carried_into_the_violation(): void
    {
        $violations = $this->schema([
            'features' => ConfigType::array()->required()->describe('feature flags, keyed by name'),
        ])->violations([]);

        self::assertStringContainsString('feature flags, keyed by name', $violations[0]->message);
    }

    /**
     * @param array<string, ConfigType> $schema
     */
    private function schema(array $schema): ConfigSchema
    {
        return ConfigSchema::fromArray($schema);
    }
}
