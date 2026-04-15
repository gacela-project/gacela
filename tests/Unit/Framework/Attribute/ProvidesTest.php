<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Attribute;
use Gacela\Framework\Attribute\Provides;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ProvidesTest extends TestCase
{
    public function test_stores_id(): void
    {
        $attribute = new Provides('my_service');

        self::assertSame('my_service', $attribute->id);
    }

    public function test_attribute_targets_methods(): void
    {
        $reflection = new ReflectionClass(Provides::class);
        $attributes = $reflection->getAttributes(Attribute::class);

        self::assertCount(1, $attributes);
        /** @var Attribute $instance */
        $instance = $attributes[0]->newInstance();
        self::assertSame(Attribute::TARGET_METHOD, $instance->flags);
    }
}
