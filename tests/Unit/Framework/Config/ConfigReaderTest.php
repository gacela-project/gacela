<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\ConfigInit;
use Gacela\Framework\Config\ConfigReaderException;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaJsonConfig;
use Gacela\Framework\Config\GacelaJsonConfigFactoryInterface;
use Gacela\Framework\Config\PathFinderInterface;
use PHPUnit\Framework\TestCase;

final class ConfigReaderTest extends TestCase
{
    public function test_no_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaJsonConfigFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaJsonConfig')
            ->willReturn(GacelaJsonConfig::withDefaults());

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            [
                'php' => $this->createStub(ConfigReaderInterface::class),
            ]
        );

        self::assertSame([], $configInit->readAll());
    }

    public function test_non_supported_reader_type(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaJsonConfigFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaJsonConfig')
            ->willReturn(GacelaJsonConfig::fromArray([
                'config' => [
                    'type' => 'non-supported-type',
                    'path' => 'path-value',
                    'path_local' => 'path_local-value',
                ],
            ]));

        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn(['path1']);

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $pathFinder,
            []
        );

        $this->expectException(ConfigReaderException::class);
        $configInit->readAll();
    }

    public function test_read_single_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaJsonConfigFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaJsonConfig')
            ->willReturn(GacelaJsonConfig::fromArray([
                'config' => [
                    [
                        'type' => 'supported-type',
                    ],
                ],
            ]));

        $reader = $this->createStub(ConfigReaderInterface::class);
        $reader->method('canRead')->willReturn(true);
        $reader->method('read')->willReturn(['key' => 'value']);

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            [
                'supported-type' => $reader,
            ]
        );

        self::assertSame(['key' => 'value'], $configInit->readAll());
    }

    public function test_read_multiple_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaJsonConfigFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaJsonConfig')
            ->willReturn(GacelaJsonConfig::fromArray([
                'config' => [
                    [
                        'type' => 'supported-type1',
                    ],
                    [
                        'type' => 'supported-type2',
                    ],
                ],
            ]));

        $reader1 = $this->createStub(ConfigReaderInterface::class);
        $reader1->method('canRead')->willReturn(true);
        $reader1->method('read')->willReturn(['key1' => 'value1']);

        $reader2 = $this->createStub(ConfigReaderInterface::class);
        $reader2->method('canRead')->willReturn(true);
        $reader2->method('read')->willReturn(['key2' => 'value2']);

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            [
                'supported-type1' => $reader1,
                'supported-type2' => $reader2,
            ]
        );

        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $configInit->readAll());
    }
}
