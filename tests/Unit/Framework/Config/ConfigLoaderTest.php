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

use function is_string;

final class ConfigLoaderTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/gacela-config-loader-' . uniqid('', true);
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->tempDir . '/*') as $file) {
            if (is_string($file)) {
                @unlink($file);
            }
        }

        @rmdir($this->tempDir);
    }

    /**
     * `doctor` compares the merged-config cache against these, so they have to
     * be the files `loadAll()` reads and nothing else.
     */
    public function test_source_files_lists_the_pattern_matches_and_the_local_override(): void
    {
        $default = $this->writeFile('default.php');
        $env = $this->writeFile('default.dev.php');
        $local = $this->writeFile('local.php');

        $loader = $this->loaderFor(
            local: $local,
            patternMatches: [$default],
            envPatternMatches: [$env],
        );

        self::assertSame([$default, $env, $local], $loader->sourceFiles());
    }

    /**
     * The local override is a computed path rather than a glob match, so it is
     * usually absent. Listing it anyway would make `doctor` report a missing
     * source on every application that does not use one.
     */
    public function test_source_files_omits_a_local_override_that_does_not_exist(): void
    {
        $default = $this->writeFile('default.php');

        $loader = $this->loaderFor(
            local: $this->tempDir . '/local.php',
            patternMatches: [$default],
            envPatternMatches: [],
        );

        self::assertSame([$default], $loader->sourceFiles());
    }

    /**
     * The local file may also match a pattern -- the case `loadAll()` guards
     * against reading twice. Here it must appear once.
     */
    public function test_source_files_lists_a_local_override_matching_a_pattern_once(): void
    {
        $default = $this->writeFile('default.php');
        $local = $this->writeFile('local.php');

        $loader = $this->loaderFor(
            local: $local,
            patternMatches: [$default, $local],
            envPatternMatches: [],
        );

        self::assertSame([$default, $local], $loader->sourceFiles());
    }

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

        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn('pattern');
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn('pattern-env');
        $normalizer->method('normalizePathPatternsWithSuffixes')->willReturn(['pattern-env']);
        $normalizer->method('normalizePathLocal')->willReturn('/project/config/local.php');

        $pathFinder = $this->createMock(PathFinderInterface::class);
        $pathFinder->expects($this->exactly(2))->method('matchingPattern')->willReturnMap([
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

    /**
     * @param list<string> $patternMatches
     * @param list<string> $envPatternMatches
     */
    private function loaderFor(string $local, array $patternMatches, array $envPatternMatches): ConfigLoader
    {
        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn('pattern');
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn('pattern-env');
        $normalizer->method('normalizePathPatternsWithSuffixes')->willReturn(['pattern-env']);
        $normalizer->method('normalizePathLocal')->willReturn($local);

        $pathFinder = $this->createStub(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturnMap([
            ['pattern', $patternMatches],
            ['pattern-env', $envPatternMatches],
        ]);

        $gacelaConfigFile = new GacelaConfigFile();
        $gacelaConfigFile->setConfigItems([
            new GacelaConfigItem('', '', $this->createStub(ConfigReaderInterface::class)),
        ]);

        return new ConfigLoader($gacelaConfigFile, $pathFinder, $normalizer);
    }

    private function writeFile(string $name): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, '<?php return [];');

        return $path;
    }
}
