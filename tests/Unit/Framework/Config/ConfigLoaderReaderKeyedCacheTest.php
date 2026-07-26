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

/**
 * The read cache is keyed by reader as well as by path.
 *
 * `ConfigLoader` memoizes reads so a file matched by both a glob and the local
 * override is parsed once. That cache was keyed by path alone, so two config
 * items pointing at the same path with *different* readers shared one entry:
 * the second reader was never called and silently received whatever the first
 * had parsed. A project reading one file through two formats — or through a
 * custom reader alongside the default — got the wrong reader's interpretation
 * with no error.
 *
 * `ConfigLoaderReadCountTest` pins the memoization itself; nothing pinned the
 * keying, which is the part that was wrong. Written while inventorying the
 * cache bugs for #539.
 */
final class ConfigLoaderReaderKeyedCacheTest extends TestCase
{
    private const string SHARED_PATH = '/app/config/shared.php';

    /** @var array<string,int> */
    private array $readsPerReader = [];

    public function test_two_readers_on_the_same_path_do_not_share_a_cache_entry(): void
    {
        $loader = $this->loaderForBothReaders();

        $all = $loader->loadAll();

        self::assertSame(
            1,
            $this->readsPerReader['b'] ?? 0,
            'the second reader must be asked to read, not handed the first reader\'s parse',
        );
        self::assertArrayHasKey(
            'only-known-to-b',
            $all,
            'the second reader\'s interpretation of the file must reach the merged config',
        );
        self::assertSame('from-reader-b', $all['shared-key']);
    }

    /**
     * Both items resolve to the same absolute path -- the collision the cache
     * key has to survive. Reader B is registered second, so with the merge
     * order its values win; under the pre-fix keying it was never consulted.
     */
    private function loaderForBothReaders(): ConfigLoader
    {
        $readerA = $this->createRecordingReader('a', ['shared-key' => 'from-reader-a']);
        $readerB = $this->createRecordingReader('b', [
            'shared-key' => 'from-reader-b',
            'only-known-to-b' => true,
        ]);

        $pathFinder = $this->createStub(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturn([self::SHARED_PATH]);

        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn(self::SHARED_PATH);
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn(self::SHARED_PATH);
        $normalizer->method('normalizePathLocal')->willReturn(self::SHARED_PATH);

        return new ConfigLoader(
            (new GacelaConfigFile())->setConfigItems([
                new GacelaConfigItem('config/*.php', 'config/local.php', $readerA),
                new GacelaConfigItem('config/*.php', 'config/local.php', $readerB),
            ]),
            $pathFinder,
            $normalizer,
        );
    }

    /**
     * @param array<string,mixed> $content
     */
    private function createRecordingReader(string $name, array $content): ConfigReaderInterface
    {
        return new class($name, $content, $this->readsPerReader) implements ConfigReaderInterface {
            /**
             * @param array<string,mixed> $content
             * @param array<string,int> $readsPerReader
             */
            public function __construct(
                private readonly string $name,
                private readonly array $content,
                private array &$readsPerReader,
            ) {
            }

            public function read(string $absolutePath): array
            {
                $this->readsPerReader[$this->name] = ($this->readsPerReader[$this->name] ?? 0) + 1;

                return $this->content;
            }
        };
    }
}
