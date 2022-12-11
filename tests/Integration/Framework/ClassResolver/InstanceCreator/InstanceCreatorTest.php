<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\InstanceCreator;

use Gacela\Framework\ClassResolver\InstanceCreator\InstanceCreator;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomClassWithDependencies;
use GacelaTest\Fixtures\StringValue;
use GacelaTest\Fixtures\StringValueInterface;
use PHPUnit\Framework\TestCase;

final class InstanceCreatorTest extends TestCase
{
    public function test_create_class_does_not_exists(): void
    {
        $gacelaConfigFile = $this->createStub(GacelaConfigFileInterface::class);
        $instanceCreator = new InstanceCreator($gacelaConfigFile);

        self::assertNull($instanceCreator->createByClassName('non-existing-class'));
    }

    public function test_create_class_without_dependencies(): void
    {
        $gacelaConfigFile = $this->createStub(GacelaConfigFileInterface::class);
        $instanceCreator = new InstanceCreator($gacelaConfigFile);

        self::assertEquals(
            new CustomClass(),
            $instanceCreator->createByClassName(CustomClass::class),
        );
    }

    public function test_create_class_with_dependencies(): void
    {
        $gacelaConfigFile = $this->createStub(GacelaConfigFileInterface::class);
        $gacelaConfigFile->method('getMappingInterfaces')->willReturn([
            StringValueInterface::class => new StringValue('custom-string'),
        ]);

        $instanceCreator = new InstanceCreator($gacelaConfigFile);

        self::assertEquals(
            new CustomClassWithDependencies(new StringValue('custom-string')),
            $instanceCreator->createByClassName(CustomClassWithDependencies::class),
        );
    }

    public function test_caching_dependencies(): void
    {
        $gacelaConfigFile = $this->createMock(GacelaConfigFileInterface::class);
        $gacelaConfigFile
            ->expects(self::once())
            ->method('getMappingInterfaces')
            ->willReturn([StringValueInterface::class => new StringValue('custom-string')]);

        $instanceCreator = new InstanceCreator($gacelaConfigFile);
        $actual1 = $instanceCreator->createByClassName(CustomClassWithDependencies::class);
        $actual2 = $instanceCreator->createByClassName(CustomClassWithDependencies::class);

        self::assertEquals($actual1, $actual2);
        self::assertNotSame($actual1, $actual2);
    }
}
