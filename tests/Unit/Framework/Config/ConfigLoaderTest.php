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

    /**
     * Bootstrap reads `gacela.php` more than once, so the same path arrives as
     * several config items. One path declared is one path to report on.
     */
    public function test_the_same_path_declared_by_several_items_counts_once(): void
    {
        $default = $this->writeFile('default.php');

        $loader = $this->loaderFor(
            local: $this->tempDir . '/local.php',
            patternMatches: [$default],
            envPatternMatches: [],
            configItemCount: 3,
        );

        self::assertSame(['pattern'], $loader->declaredPatterns());
    }

    /**
     * Several paths, one of them declared twice and one matching nothing: both
     * answers have to come back as lists, numbered from zero, or the caller
     * reporting "1 of 2" reads a gap where a pattern used to be.
     */
    public function test_distinct_paths_are_listed_in_order_with_the_duplicate_collapsed(): void
    {
        $present = $this->writeFile('default.php');

        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')
            ->willReturnCallback(static fn (GacelaConfigItem $item): string => 'pattern-' . $item->path());
        $normalizer->method('normalizePathPatternsWithSuffixes')->willReturn([]);
        $normalizer->method('normalizePathLocal')->willReturn($this->tempDir . '/local.php');

        $pathFinder = $this->createStub(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturnMap([
            ['pattern-a', [$present]],
            ['pattern-b', []],
            ['pattern-c', []],
        ]);

        $gacelaConfigFile = new GacelaConfigFile();
        $gacelaConfigFile->setConfigItems([
            new GacelaConfigItem('a', '', $this->createStub(ConfigReaderInterface::class)),
            new GacelaConfigItem('a', '', $this->createStub(ConfigReaderInterface::class)),
            new GacelaConfigItem('b', '', $this->createStub(ConfigReaderInterface::class)),
            new GacelaConfigItem('b', '', $this->createStub(ConfigReaderInterface::class)),
            new GacelaConfigItem('c', '', $this->createStub(ConfigReaderInterface::class)),
        ]);

        $loader = new ConfigLoader($gacelaConfigFile, $pathFinder, $normalizer);

        self::assertSame(['pattern-a', 'pattern-b', 'pattern-c'], $loader->declaredPatterns());
        self::assertSame(['pattern-b', 'pattern-c'], $loader->patternsMatchingNothing());
    }

    public function test_a_base_pattern_matching_files_is_not_reported(): void
    {
        $default = $this->writeFile('default.php');

        $loader = $this->loaderFor(
            local: $this->tempDir . '/local.php',
            patternMatches: [$default],
            envPatternMatches: [],
        );

        self::assertSame([], $loader->patternsMatchingNothing());
    }

    public function test_a_base_pattern_matching_nothing_is_reported(): void
    {
        $loader = $this->loaderFor(
            local: $this->tempDir . '/local.php',
            patternMatches: [],
            envPatternMatches: [],
        );

        self::assertSame(['pattern'], $loader->patternsMatchingNothing());
    }

    /**
     * The environment file is meant to be absent everywhere it does not apply,
     * so reporting it would fire on every correctly configured project. Only
     * the base pattern is a claim that something should be there.
     */
    public function test_an_absent_environment_pattern_is_not_reported(): void
    {
        $default = $this->writeFile('default.php');

        $loader = $this->loaderFor(
            local: $this->tempDir . '/local.php',
            patternMatches: [$default],
            envPatternMatches: [],
        );

        self::assertSame([], $loader->patternsMatchingNothing());
        // ...and the environment pattern really was empty, so the case is real.
        self::assertSame([$default], $loader->sourceFiles());
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
     * `config/*.php` is globbed literally, so the base pattern also matches the
     * environment files the framework itself names -- and it matched them
     * *before* the environment chain was applied on top. A key only
     * `app-prod.php` sets had nothing in the base layer to overwrite it, so a
     * developer read the production value with nothing said (#889).
     *
     * Asserted on a key the base file does not mention, so the answer cannot
     * come out right by the order `glob()` happened to return the two files in.
     */
    public function test_the_base_pattern_skips_an_environment_layer_of_a_file_it_matched(): void
    {
        $base = $this->pathTo('app.php');
        $layer = $this->pathTo('app-prod.php');

        $loader = $this->loaderReading([
            $base => ['billing.currency' => 'EUR'],
            $layer => ['payment.endpoint' => 'live'],
        ], patternMatches: [$base, $layer]);

        self::assertSame(['billing.currency' => 'EUR'], $loader->loadAll());
        self::assertSame([$base], $loader->sourceFiles());
    }

    /**
     * The middle link of the chain may not exist -- a project with `app.php` and
     * `app-prod-eu.php` and no `app-prod.php` -- so one strip is not enough to
     * recognise the layer.
     */
    public function test_a_layer_is_recognised_across_a_missing_middle_link(): void
    {
        $base = $this->pathTo('app.php');
        $layer = $this->pathTo('app-prod-eu.php');

        $loader = $this->loaderReading([
            $base => ['billing.currency' => 'EUR'],
            $layer => ['billing.vat_rate_bp' => 1900],
        ], patternMatches: [$base, $layer]);

        self::assertSame(['billing.currency' => 'EUR'], $loader->loadAll());
    }

    /**
     * The rule is about a file that is a layer *of another file the same pattern
     * matched*. With no `app.php` beside it, `app-prod.php` is the only thing
     * the pattern found and is the base layer itself -- dropping it would turn a
     * silent wrong value into a silent missing one.
     */
    public function test_a_suffixed_file_with_no_base_beside_it_is_the_base_layer(): void
    {
        $only = $this->pathTo('app-prod.php');

        $loader = $this->loaderReading([
            $only => ['payment.endpoint' => 'live'],
        ], patternMatches: [$only]);

        self::assertSame(['payment.endpoint' => 'live'], $loader->loadAll());
        self::assertSame([$only], $loader->sourceFiles());
    }

    /**
     * Nothing to strip, so nothing to exclude: the local override conventionally
     * sits in the same directory as the base file and matches the same pattern.
     */
    public function test_a_file_whose_name_carries_no_suffix_is_never_a_layer(): void
    {
        $base = $this->pathTo('app.php');
        $other = $this->pathTo('queue.php');

        $loader = $this->loaderReading([
            $base => ['billing.currency' => 'EUR'],
            $other => ['queue.workers' => 4],
        ], patternMatches: [$base, $other]);

        self::assertSame(
            ['billing.currency' => 'EUR', 'queue.workers' => 4],
            $loader->loadAll(),
        );
    }

    /**
     * The suffix goes before the *first* dot, which is where
     * {@see \Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy}
     * puts it, so a multi-part filename is stripped the same way it is built.
     */
    public function test_a_layer_of_a_multi_part_filename_is_stripped_before_the_first_dot(): void
    {
        $base = $this->pathTo('default.app.php');
        $layer = $this->pathTo('default-prod.app.php');

        $loader = $this->loaderReading([
            $base => ['billing.currency' => 'EUR'],
            $layer => ['payment.endpoint' => 'live'],
        ], patternMatches: [$base, $layer]);

        self::assertSame(['billing.currency' => 'EUR'], $loader->loadAll());
    }

    /**
     * `doctor` reports these, and it reads them from the loader rather than
     * re-globbing, so the files it names are the ones the base layer really
     * skipped.
     */
    public function test_the_excluded_environment_layers_are_reported_with_their_base_and_suffix(): void
    {
        $base = $this->pathTo('app.php');
        $layer = $this->pathTo('app-prod.php');

        $loader = $this->loaderReading([], patternMatches: [$base, $layer]);

        $excluded = $loader->excludedEnvironmentLayers();

        self::assertCount(1, $excluded);
        self::assertSame($layer, $excluded[0]->path);
        self::assertSame($base, $excluded[0]->basePath);
        self::assertSame('prod', $excluded[0]->suffix);
    }

    public function test_nothing_is_reported_as_excluded_when_the_base_pattern_matched_one_file(): void
    {
        $loader = $this->loaderReading([], patternMatches: [$this->pathTo('app.php')]);

        self::assertSame([], $loader->excludedEnvironmentLayers());
    }

    /**
     * A base pattern that matched a base file and its environment layers still
     * matched something, so it is not one of the paths that load nothing.
     */
    public function test_a_base_pattern_matching_only_a_file_and_its_layers_is_not_reported(): void
    {
        $base = $this->pathTo('app.php');
        $layer = $this->pathTo('app-prod.php');

        $loader = $this->loaderReading([], patternMatches: [$base, $layer]);

        self::assertSame([], $loader->patternsMatchingNothing());
    }

    /**
     * @param array<string,array<string,mixed>> $valuesByPath
     * @param list<string> $patternMatches
     */
    private function loaderReading(array $valuesByPath, array $patternMatches): ConfigLoader
    {
        $reader = new class($valuesByPath) implements ConfigReaderInterface {
            /**
             * @param array<string,array<string,mixed>> $valuesByPath
             */
            public function __construct(
                private readonly array $valuesByPath,
            ) {
            }

            public function read(string $absolutePath): array
            {
                return $this->valuesByPath[$absolutePath] ?? [];
            }
        };

        $normalizer = $this->createStub(PathNormalizerInterface::class);
        $normalizer->method('normalizePathPattern')->willReturn('pattern');
        $normalizer->method('normalizePathPatternWithEnvironment')->willReturn('pattern-env');
        $normalizer->method('normalizePathPatternsWithSuffixes')->willReturn(['pattern-env']);
        $normalizer->method('normalizePathLocal')->willReturn($this->pathTo('local.php'));

        $pathFinder = $this->createStub(PathFinderInterface::class);
        $pathFinder->method('matchingPattern')->willReturnMap([
            ['pattern', $patternMatches],
            ['pattern-env', []],
        ]);

        $gacelaConfigFile = new GacelaConfigFile();
        $gacelaConfigFile->setConfigItems([new GacelaConfigItem('', '', $reader)]);

        return new ConfigLoader($gacelaConfigFile, $pathFinder, $normalizer);
    }

    /**
     * Built with the platform separator: the exclusion has to find the filename
     * inside an absolute path on Windows too, where every PR is also run.
     */
    private function pathTo(string $name): string
    {
        return $this->tempDir . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * @param list<string> $patternMatches
     * @param list<string> $envPatternMatches
     */
    private function loaderFor(
        string $local,
        array $patternMatches,
        array $envPatternMatches,
        int $configItemCount = 1,
    ): ConfigLoader {
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

        $configItems = [];
        for ($i = 0; $i < $configItemCount; ++$i) {
            $configItems[] = new GacelaConfigItem('', '', $this->createStub(ConfigReaderInterface::class));
        }

        $gacelaConfigFile = new GacelaConfigFile();
        $gacelaConfigFile->setConfigItems($configItems);

        return new ConfigLoader($gacelaConfigFile, $pathFinder, $normalizer);
    }

    private function writeFile(string $name): string
    {
        $path = $this->tempDir . '/' . $name;
        file_put_contents($path, '<?php return [];');

        return $path;
    }
}
