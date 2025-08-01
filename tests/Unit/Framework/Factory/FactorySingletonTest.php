<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Factory;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;
use PHPUnit\Framework\TestCase;

final class FactorySingletonTest extends TestCase
{
    public function test_singleton_returns_same_instance(): void
    {
        $factory = new class() extends AbstractFactory {
            public function createObject(): CustomInterface
            {
                return $this->singleton(CustomInterface::class, static fn (): \GacelaTest\Fixtures\CustomClass => new CustomClass());
            }
        };
        $a = $factory->createObject();
        $b = $factory->createObject();

        self::assertSame($a, $b);
    }

    public function test_without_singleton_returns_new_instance(): void
    {
        $factory = new class() extends AbstractFactory {
            public function createObject(): CustomInterface
            {
                return new CustomClass();
            }
        };

        $a = $factory->createObject();
        $b = $factory->createObject();

        self::assertNotSame($a, $b);
    }
}
