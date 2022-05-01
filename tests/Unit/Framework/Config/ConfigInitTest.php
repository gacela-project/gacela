<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Config;

use Gacela\Framework\Config\ConfigLoader;
use Gacela\Framework\Config\ConfigReaderInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigItem;
use Gacela\Framework\Config\PathFinderInterface;
use Gacela\Framework\Config\PathNormalizerInterface;
use PHPUnit\Framework\TestCase;

final class ConfigInitTest extends TestCase
{
    public function test_no_config(): void
    {
        $configInit = new ConfigLoader(
            new GacelaConfigFile(),
            $this->createMock(PathFinderInterface::class),
            $this->createMock(PathNormalizerInterface::class)
        );

        self::assertSame([], $configInit->loadAll());
    }

    public function test_one_reader_linked_to_unsupported_type_is_ignored(): void
    {
        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn(['path1']);

        $configInit = new ConfigLoader(
            new GacelaConfigFile(),
            $pathFinder,
            $this->createMock(PathNormalizerInterface::class)
        );

        self::assertSame([], $configInit->loadAll());
    }

    public function test_no_readers_returns_empty_array(): void
    {
        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn(['path1']);

        $configInit = new ConfigLoader(
            new GacelaConfigFile(),
            $pathFinder,
            $this->createMock(PathNormalizerInterface::class),
        );

        self::assertSame([], $configInit->loadAll());
    }

    public function test_read_single_config(): void
    {
        $reader = $this->createStub(ConfigReaderInterface::class);
        $reader->method('read')->willReturn(['key' => 'value']);

        $gacelaConfigFile = (new GacelaConfigFile())->setConfigItems([
            new GacelaConfigItem('path', 'path_local', $reader),
        ]);

        $configInit = new ConfigLoader(
            $gacelaConfigFile,
            $this->createMock(PathFinderInterface::class),
            $this->createMock(PathNormalizerInterface::class)
        );

        self::assertSame(['key' => 'value'], $configInit->loadAll());
    }

    public function test_read_multiple_config(): void
    {
        $reader1 = $this->createStub(ConfigReaderInterface::class);
        $reader1->method('read')->willReturn(['key1' => 'value1']);

        $reader2 = $this->createStub(ConfigReaderInterface::class);
        $reader2->method('read')->willReturn(['key2' => 'value2']);

        $gacelaConfigFile = (new GacelaConfigFile())->setConfigItems([
            new GacelaConfigItem('path', 'path_local', $reader1),
            new GacelaConfigItem('path', 'path_local', $reader2),
        ]);

        $configInit = new ConfigLoader(
            $gacelaConfigFile,
            $this->createMock(PathFinderInterface::class),
            $this->createMock(PathNormalizerInterface::class),
        );

        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
        ], $configInit->loadAll());
    }
}
