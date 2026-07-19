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

final class ConfigLoaderReadCountTest extends TestCase
{
    /** @var array<string,int> */
    private array $readsPerPath = [];

    public function test_each_config_file_is_read_at_most_once_per_load(): void
    {
        $reader = $this->createCountingReader([
            '/app/config/default.php' => ['a' => 1],
            '/app/config/local.php' => ['b' => 2],
        ]);

        $configItem = new GacelaConfigItem('config/*.php', 'config/local.php', $reader);

        $pathFinder = $this->createStub(PathFinderInterface::class);
        // The local file also matches the glob pattern: it must still be
        // read only once (excluded from the pattern phase, read as local).
        $pathFinder->method('matchingPattern')
            ->willReturn(['/app/config/default.php', '/app/config/local.php']);

        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn('/app/config/*.php');
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn('/app/config/*-dev.php');
        $normalizer->method('normalizePathLocal')->willReturn('/app/config/local.php');

        $loader = new ConfigLoader(
            (new GacelaConfigFile())->setConfigItems([$configItem]),
            $pathFinder,
            $normalizer,
        );

        self::assertSame(['a' => 1, 'b' => 2], $loader->loadAll());
        self::assertSame(1, $this->readsPerPath['/app/config/default.php']);
        self::assertSame(1, $this->readsPerPath['/app/config/local.php']);
    }

    /**
     * @param array<string,array<string,mixed>> $contentByPath
     */
    private function createCountingReader(array $contentByPath): ConfigReaderInterface
    {
        $this->readsPerPath = [];

        return new class($contentByPath, $this->readsPerPath) implements ConfigReaderInterface {
            /**
             * @param array<string,array<string,mixed>> $contentByPath
             * @param array<string,int> $readsPerPath
             */
            public function __construct(
                private readonly array $contentByPath,
                private array &$readsPerPath,
            ) {
            }

            public function read(string $absolutePath): array
            {
                $this->readsPerPath[$absolutePath] = ($this->readsPerPath[$absolutePath] ?? 0) + 1;

                return $this->contentByPath[$absolutePath] ?? [];
            }
        };
    }
}
