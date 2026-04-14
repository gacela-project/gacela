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

final class ConfigLoaderTest extends TestCase
{
    public function test_load_all_skips_local_override_from_pattern_matches(): void
    {
        $reader = new class() implements ConfigReaderInterface {
            /** @var array<string,int> */
            public array $readCalls = [];

            public function read(string $absolutePath): array
            {
                $this->readCalls[$absolutePath] = ($this->readCalls[$absolutePath] ?? 0) + 1;
                return match ($absolutePath) {
                    '/project/config/default.php' => ['from' => 'default'],
                    '/project/config/local.php' => ['from' => 'local'],
                    default => [],
                };
            }
        };

        $configItem = new GacelaConfigItem('', '', $reader);

        $normalizer = $this->createMock(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn('pattern');
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn('pattern-env');
        $normalizer->method('normalizePathLocal')->willReturn('/project/config/local.php');

        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturnMap([
            ['pattern', ['/project/config/default.php', '/project/config/local.php']],
            ['pattern-env', []],
        ]);

        $gacelaConfigFile = new GacelaConfigFile();
        $gacelaConfigFile->setConfigItems([$configItem]);

        $loader = new ConfigLoader($gacelaConfigFile, $pathFinder, $normalizer);
        $result = $loader->loadAll();

        self::assertSame('local', $result['from']);
        // Without exclusion, the local path would be read in pattern matching AND in
        // loadLocalConfig. The exclusion guarantees it only enters via loadLocalConfig.
        self::assertSame(1, $reader->readCalls['/project/config/local.php'] ?? 0);
    }
}
