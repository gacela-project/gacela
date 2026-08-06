<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Console\CacheWarm;

use Error;
use Gacela\Console\Application\CacheWarm\CacheWarmOutputFormatter;
use Gacela\Console\Application\CacheWarm\CacheWarmService;
use Gacela\Console\Application\CacheWarm\ModuleWarmer;
use Gacela\Console\Domain\AllAppModules\AppModule;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNamePhpCache;
use Gacela\Framework\ClassResolver\Cache\CustomServicesPhpCache;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmRepository;
use GacelaTest\Integration\Console\CacheWarm\AttributeWarm\AttributeWarmService;
use GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Healthy\HealthyFacade;
use GacelaTest\Integration\Console\CacheWarm\FacadeWarm\Domain\Healthy\HealthyFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

use function implode;
use function is_dir;
use function rmdir;
use function spl_autoload_register;
use function spl_autoload_unregister;
use function uniqid;
use function unlink;

use const DIRECTORY_SEPARATOR;
use const PHP_EOL;

/**
 * Pins what `cache:warm` reports and counts per module: every pillar that
 * resolves, every one that is missing, and every one whose autoloading blows
 * up have to be told apart in both the counters and the output.
 */
final class ModuleWarmerTest extends TestCase
{
    /** @var class-string */
    private const EXPLODING_CLASS = 'GacelaTest\\Integration\\Console\\CacheWarm\\Exploding\\ExplodingFactory';

    private string $cacheDir;

    private BufferedOutput $output;

    private ModuleWarmer $warmer;

    protected function setUp(): void
    {
        $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '.gacela-cache-' . uniqid('', true);

        $cacheDir = $this->cacheDir;
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use ($cacheDir): void {
            $config->resetInMemoryCache();
            $config->setFileCache(true, $cacheDir);
            $config->setProjectNamespaces(['GacelaTest\\Integration\\Console\\CacheWarm\\FacadeWarm\\Domain']);
        });

        ClassNamePhpCache::clearStaticCache();

        $this->output = new BufferedOutput();
        $this->warmer = new ModuleWarmer(new CacheWarmService(), new CacheWarmOutputFormatter($this->output));
    }

    protected function tearDown(): void
    {
        ClassNamePhpCache::clearStaticCache();
        $this->removeCacheDir();
    }

    public function test_every_resolved_pillar_is_counted_and_reported(): void
    {
        $module = new AppModule('Healthy', 'Healthy', HealthyFacade::class, HealthyFactory::class);

        [$resolved, $skipped, $failed] = $this->warmer->warmModule($module, warmAttributes: false);

        self::assertSame(2, $resolved);
        self::assertSame(0, $skipped);
        self::assertSame(0, $failed);
        self::assertSame($this->lines('Processing: Healthy', '  ✓ Resolved Facade: ' . HealthyFacade::class, '  ✓ Resolved Factory: ' . HealthyFactory::class, ''), $this->output->fetch());
    }

    public function test_a_missing_pillar_class_is_skipped_not_failed(): void
    {
        /** @var class-string $missingFactory */
        $missingFactory = 'Missing\\Factory';
        $module = new AppModule('Healthy', 'Healthy', HealthyFacade::class, $missingFactory);

        [$resolved, $skipped, $failed] = $this->warmer->warmModule($module, warmAttributes: false);

        self::assertSame(1, $resolved);
        self::assertSame(1, $skipped);
        self::assertSame(0, $failed);
        self::assertSame($this->lines('Processing: Healthy', '  ✓ Resolved Facade: ' . HealthyFacade::class, '  ⚠ Skipped Factory: Missing\\Factory (class not found)', ''), $this->output->fetch());
    }

    public function test_a_pillar_whose_autoloading_throws_is_reported_as_failed(): void
    {
        $autoloader = static function (string $class): void {
            if ($class === self::EXPLODING_CLASS) {
                throw new Error('autoloading blew up');
            }
        };
        spl_autoload_register($autoloader);

        try {
            $module = new AppModule('Healthy', 'Healthy', HealthyFacade::class, self::EXPLODING_CLASS);

            [$resolved, $skipped, $failed] = $this->warmer->warmModule($module, warmAttributes: false);
        } finally {
            spl_autoload_unregister($autoloader);
        }

        self::assertSame(1, $resolved);
        // A failure is not a skip. Counting it as one is what made
        // `CacheWarmedEvent` report a healthy deploy as a broken one.
        self::assertSame(0, $skipped);
        self::assertSame(1, $failed);
        self::assertSame($this->lines('Processing: Healthy', '  ✓ Resolved Facade: ' . HealthyFacade::class, '  ✗ Failed Factory: ' . self::EXPLODING_CLASS . ' (autoloading blew up)', ''), $this->output->fetch());
    }

    public function test_attribute_warming_only_happens_when_it_is_asked_for(): void
    {
        CustomServicesPhpCache::clearStaticCache();

        $module = new AppModule('AttributeWarm', 'AttributeWarm', AttributeWarmService::class);

        $this->warmer->warmModule($module, warmAttributes: false);
        self::assertSame([], CustomServicesPhpCache::all());

        $this->warmer->warmModule($module, warmAttributes: true);
        self::assertSame(
            [AttributeWarmService::class . '::getRepository' => AttributeWarmRepository::class],
            CustomServicesPhpCache::all(),
        );
    }

    public function test_counts_are_accumulated_across_modules(): void
    {
        /** @var class-string $missingFactory */
        $missingFactory = 'Missing\\Factory';

        $first = new AppModule('Healthy', 'Healthy', HealthyFacade::class, HealthyFactory::class);
        $second = new AppModule('Healthy', 'Healthy', HealthyFacade::class, $missingFactory);

        self::assertSame([3, 1, 0], $this->warmer->warmModules([$first, $second], warmAttributes: false));
    }

    /**
     * The test above has only one module skipping anything, so `+=` and `=`
     * produce the same total and nothing distinguishes accumulating from
     * overwriting -- which is exactly the mutant that escaped the full run.
     * Two modules skipping one pillar each tell them apart: 2, not 1.
     */
    public function test_skipped_counts_accumulate_rather_than_overwrite(): void
    {
        /** @var class-string $missingFactory */
        $missingFactory = 'Missing\\Factory';

        $first = new AppModule('Healthy', 'Healthy', HealthyFacade::class, $missingFactory);
        $second = new AppModule('Healthy', 'Healthy', HealthyFacade::class, $missingFactory);

        self::assertSame([2, 2, 0], $this->warmer->warmModules([$first, $second], warmAttributes: false));
    }

    private function lines(string ...$lines): string
    {
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function removeCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }

        foreach (glob($this->cacheDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->cacheDir);
    }
}
