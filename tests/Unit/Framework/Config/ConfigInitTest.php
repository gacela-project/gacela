<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\ConfigInit;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathFinderInterface;
use PHPUnit\Framework\TestCase;

final class ConfigInitTest extends TestCase
{
    public function test_no_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaConfigFileFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaFileConfig')
            ->willReturn(GacelaConfigFile::withDefaults());

        $readers = [
            'php' => $this->createStub(ConfigReaderInterface::class),
        ];

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            $readers
        );

        self::assertSame([], $configInit->readAll());
    }

    public function test_one_reader_linked_to_unsupported_type_is_ignored(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaConfigFileFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaFileConfig')
            ->willReturn(GacelaConfigFile::withDefaults());

        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn(['path1']);

        $readers = [
            'unsupported_type' => $this->createStub(ConfigReaderInterface::class),
        ];

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $pathFinder,
            $readers
        );

        self::assertSame([], $configInit->readAll());
    }

    public function test_no_readers_returns_empty_array(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaConfigFileFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaFileConfig')
            ->willReturn(GacelaConfigFile::withDefaults());

        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn(['path1']);

        $readers = [];

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $pathFinder,
            $readers
        );

        self::assertSame([], $configInit->readAll());
    }

    public function test_read_single_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaConfigFileFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaFileConfig')
            ->willReturn((new GacelaConfigFile())
                ->setConfigItems([
                    'supported-type' => new GacelaConfigItem('supported-type'),
                ]));

        $reader = $this->createStub(ConfigReaderInterface::class);
        $reader->method('canRead')->willReturn(true);
        $reader->method('read')->willReturn(['key' => 'value']);

        $readers = [
            'supported-type' => $reader,
        ];

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            $readers
        );

        self::assertSame(['key' => 'value'], $configInit->readAll());
    }

    public function test_read_multiple_config(): void
    {
        $gacelaJsonConfigCreator = $this->createStub(GacelaConfigFileFactoryInterface::class);
        $gacelaJsonConfigCreator
            ->method('createGacelaFileConfig')
            ->willReturn((new GacelaConfigFile())
                ->setConfigItems([
                    'supported-type1' => new GacelaConfigItem('supported-type1'),
                    'supported-type2' => new GacelaConfigItem('supported-type2'),
                ]));

        $reader1 = $this->createStub(ConfigReaderInterface::class);
        $reader1->method('canRead')->willReturn(true);
        $reader1->method('read')->willReturn(['key1' => 'value1']);

        $reader2 = $this->createStub(ConfigReaderInterface::class);
        $reader2->method('canRead')->willReturn(true);
        $reader2->method('read')->willReturn(['key2' => 'value2']);

        $readers = [
            'supported-type1' => $reader1,
            'supported-type2' => $reader2,
        ];

        $configInit = new ConfigInit(
            'application_root_dir',
            $gacelaJsonConfigCreator,
            $this->createMock(PathFinderInterface::class),
            $readers
        );

        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $configInit->readAll());
    }
}
