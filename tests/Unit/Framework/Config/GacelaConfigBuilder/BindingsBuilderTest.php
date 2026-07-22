<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config\GacelaConfigBuilder;

use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use PHPUnit\Framework\TestCase;

final class BindingsBuilderTest extends TestCase
{
    public function test_bind_registers_a_value_under_its_key(): void
    {
        $builder = new BindingsBuilder();

        $builder->bind('App\\Port', 'App\\Adapter');

        self::assertSame(['App\\Port' => 'App\\Adapter'], $builder->build());
    }

    public function test_bind_overwrites_an_existing_key(): void
    {
        $builder = new BindingsBuilder();

        $builder->bind('App\\Port', 'App\\First');
        $builder->bind('App\\Port', 'App\\Second');

        self::assertSame(['App\\Port' => 'App\\Second'], $builder->build());
    }

    public function test_bind_if_registers_when_the_key_is_absent(): void
    {
        $builder = new BindingsBuilder();

        $builder->bindIf('App\\Port', 'App\\Adapter');

        self::assertSame(['App\\Port' => 'App\\Adapter'], $builder->build());
    }

    public function test_bind_if_does_not_overwrite_an_already_bound_key(): void
    {
        $builder = new BindingsBuilder();

        $builder->bind('App\\Port', 'App\\Existing');
        $builder->bindIf('App\\Port', 'App\\Override');

        self::assertSame(['App\\Port' => 'App\\Existing'], $builder->build());
    }

    public function test_bind_if_is_chainable(): void
    {
        $builder = new BindingsBuilder();

        self::assertSame($builder, $builder->bindIf('App\\Port', 'App\\Adapter'));
    }
}
