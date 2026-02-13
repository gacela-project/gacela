<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\Deprecated;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class DeprecatedTest extends TestCase
{
    public function test_attribute_with_all_parameters(): void
    {
        $deprecated = new Deprecated(
            since: '1.5.0',
            replacement: 'NewClass',
            willRemoveIn: '2.0.0',
            reason: 'Legacy implementation',
        );

        self::assertSame('1.5.0', $deprecated->since);
        self::assertSame('NewClass', $deprecated->replacement);
        self::assertSame('2.0.0', $deprecated->willRemoveIn);
        self::assertSame('Legacy implementation', $deprecated->reason);
    }

    public function test_attribute_with_only_required_parameters(): void
    {
        $deprecated = new Deprecated(since: '1.0.0');

        self::assertSame('1.0.0', $deprecated->since);
        self::assertNull($deprecated->replacement);
        self::assertNull($deprecated->willRemoveIn);
        self::assertNull($deprecated->reason);
    }

    public function test_attribute_can_be_applied_to_class(): void
    {
        $reflection = new ReflectionClass(SampleDeprecatedClass::class);
        $attributes = $reflection->getAttributes(Deprecated::class);

        self::assertCount(1, $attributes);

        $deprecated = $attributes[0]->newInstance();
        self::assertSame('1.0.0', $deprecated->since);
    }

    public function test_attribute_can_be_applied_to_method(): void
    {
        $reflection = new ReflectionMethod(SampleDeprecatedClass::class, 'oldMethod');
        $attributes = $reflection->getAttributes(Deprecated::class);

        self::assertCount(1, $attributes);

        $deprecated = $attributes[0]->newInstance();
        self::assertSame('1.2.0', $deprecated->since);
        self::assertSame('newMethod', $deprecated->replacement);
    }
}

#[Deprecated(since: '1.0.0', replacement: 'ModernClass')]
final class SampleDeprecatedClass
{
    #[Deprecated(since: '1.2.0', replacement: 'newMethod')]
    public function oldMethod(): string
    {
        return 'old';
    }
}
