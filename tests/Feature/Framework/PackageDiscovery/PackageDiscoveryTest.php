<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\PackageDiscovery;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Package\PackageConfigCache;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Bootstrap\Package\PackageRefusal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\PackageDiscovery\App\Auditing\AuditingFacade;
use GacelaTest\Feature\Framework\PackageDiscovery\App\BootLog;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditRecorder;
use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditSinkInterface;
use GacelaTest\Feature\Framework\PackageDiscovery\Support\InstalledPackages;
use PHPUnit\Framework\TestCase;

use function array_map;
use function is_file;

/**
 * A package contributes to an application by being installed.
 *
 * Every test here asserts something the application's own `gacela.php` never
 * mentions the package to get -- which is the whole claim.
 */
final class PackageDiscoveryTest extends TestCase
{
    private InstalledPackages $installed;

    protected function setUp(): void
    {
        $this->installed = new InstalledPackages();
        AuditRecorder::reset();
        BootLog::reset();
    }

    protected function tearDown(): void
    {
        $this->installed->remove();
        Gacela::resetCache();
    }

    public function test_an_installed_package_contributes_without_the_project_naming_it(): void
    {
        $this->installed->install(['AuditTrail']);
        $this->installed->writeGacelaPhp(<<<'PHP'
            use Gacela\Framework\Bootstrap\GacelaConfig;

            return static function (GacelaConfig $config): void {
                // Nothing about any package. That is the point.
                $config->setProjectNamespaces(['GacelaTest\Feature\Framework\PackageDiscovery\App']);
            };
            PHP);

        $this->bootstrap();

        // The plugin stack: the member the package put on its own extension
        // point runs when the application's module reads the stack.
        Gacela::getRequired(AuditingFacade::class)->announce('invoice issued');

        // The listener: registered by the package's config, fired by the
        // framework at the end of the same bootstrap.
        self::assertSame(['booted', 'log: invoice issued'], AuditRecorder::records());

        // A config key the package declared, readable like any other.
        self::assertTrue(Config::getInstance()->getBool('audit.enabled'));

        // The package's own default answers, so the test below -- where the
        // project replaces it -- is about the project winning rather than about
        // the project being the only one who ever bound anything.
        self::assertSame('file (the package default)', Gacela::getRequired(AuditSinkInterface::class)->label());
    }

    public function test_the_project_has_the_last_word_on_a_binding_the_package_set(): void
    {
        $this->installed->install(['AuditTrail']);
        $this->installed->writeGacelaPhp(<<<'PHP'
            use Gacela\Framework\Bootstrap\GacelaConfig;
            use GacelaTest\Feature\Framework\PackageDiscovery\App\ProjectAuditSink;
            use GacelaTest\Feature\Framework\PackageDiscovery\Packages\AuditTrail\AuditSinkInterface;

            return static function (GacelaConfig $config): void {
                $config->addBinding(AuditSinkInterface::class, ProjectAuditSink::class);
            };
            PHP);

        $this->bootstrap();

        self::assertSame('the project decided', Gacela::getRequired(AuditSinkInterface::class)->label());
    }

    public function test_dont_discover_in_the_bootstrap_closure_refuses_a_package(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);

        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->dontDiscover(['gacela-fixture/legacy-numbering']);
        });

        self::assertTrue(Config::getInstance()->getBool('audit.enabled'));
        self::assertNull(Config::getInstance()->get('legacy.numbering', null));
        self::assertSame(['gacela-fixture/audit-trail'], $this->discoveredNames());
        self::assertSame([PackageRefusal::OptedOut], $this->refusalReasons());
    }

    /**
     * The refusal applies to the package it names and stops there: the packages
     * installed after it are read exactly as if the entry were not written.
     */
    public function test_refusing_the_first_installed_package_leaves_the_rest_discovered(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);

        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->dontDiscover(['gacela-fixture/audit-trail']);
        });

        self::assertSame(['gacela-fixture/legacy-numbering'], $this->discoveredNames());
        self::assertSame('roman', Config::getInstance()->get('legacy.numbering'));
        self::assertNull(Config::getInstance()->get('audit.enabled', null));
    }

    /**
     * Two packages declaring the same thing: the later-installed one wins, and
     * the project is still merged after both.
     *
     * Worth pinning because it is also the warning in `docs/packages.md` --
     * installed order is Composer's, decided by the dependency graph and by when
     * things were added to `composer.json`, and no application controls it. A
     * package that needs to win must not rely on this.
     */
    public function test_between_two_packages_the_later_installed_one_wins(): void
    {
        $this->installed->install(['AuditTrail', 'AuditOverride']);

        $this->bootstrap();

        self::assertSame(
            ['gacela-fixture/audit-trail', 'gacela-fixture/audit-override'],
            $this->discoveredNames(),
        );
        self::assertFalse(Config::getInstance()->getBool('audit.enabled'));
        self::assertSame(
            'the later-installed package decided',
            Gacela::getRequired(AuditSinkInterface::class)->label(),
        );
    }

    public function test_installing_the_same_two_packages_the_other_way_round_reverses_the_answer(): void
    {
        $this->installed->install(['AuditOverride', 'AuditTrail']);

        $this->bootstrap();

        self::assertSame(
            ['gacela-fixture/audit-override', 'gacela-fixture/audit-trail'],
            $this->discoveredNames(),
        );
        self::assertTrue(Config::getInstance()->getBool('audit.enabled'));
        self::assertSame(
            'file (the package default)',
            Gacela::getRequired(AuditSinkInterface::class)->label(),
        );
    }

    /**
     * The registry describes the boot that just discovered, not every boot the
     * process has seen -- so a second application, with other packages installed
     * against it, replaces the answer instead of appending to it. What `doctor`
     * and `debug:container` read afterwards is one application's packages.
     *
     * Two roots rather than two bootstraps of one, because the merged
     * configuration is memoized per root and a second bootstrap of the same one
     * discovers nothing. And neither of them clears the in-memory caches, so the
     * clearing under test is the one discovery does for itself: nothing may be
     * left to a caller that a production bootstrap does not have to make.
     */
    public function test_discovering_for_another_application_replaces_the_answer(): void
    {
        $this->installed->install(['MissingConfig', 'AuditTrail']);
        Gacela::bootstrap($this->installed->appRoot, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        $other = new InstalledPackages();

        try {
            $other->install(['LegacyNumbering']);
            Gacela::bootstrap($other->appRoot, static function (GacelaConfig $config): void {
                $config->setFileCache(false);
            });

            self::assertSame(['gacela-fixture/legacy-numbering'], $this->discoveredNames());
            self::assertSame([], $this->refusalReasons());
        } finally {
            $other->remove();
        }
    }

    /**
     * The harder half: `gacela.php` is read *after* the packages are merged, so
     * an opt-out written there is only in time because the file is read for the
     * opt-out before any package's code runs.
     */
    public function test_dont_discover_in_gacela_php_refuses_a_package(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);
        $this->installed->writeGacelaPhp(<<<'PHP'
            use Gacela\Framework\Bootstrap\GacelaConfig;

            return static function (GacelaConfig $config): void {
                $config->dontDiscover(['gacela-fixture/legacy-numbering']);
            };
            PHP);

        $this->bootstrap();

        self::assertNull(Config::getInstance()->get('legacy.numbering', null));
        self::assertSame(['gacela-fixture/audit-trail'], $this->discoveredNames());
    }

    public function test_dont_discover_everything_reads_no_package_at_all(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);

        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->dontDiscover(['*']);
        });

        self::assertSame([], $this->discoveredNames());
        self::assertTrue(PackageDiscoveryRegistry::isDisabled());
        // Nothing to list as refused: no declaration was read to name.
        self::assertSame([], PackageDiscoveryRegistry::refused());
        self::assertNull(Config::getInstance()->get('audit.enabled', null));
    }

    /**
     * Two opt-outs from two sources are two refusals, not the later one winning.
     */
    public function test_opt_outs_accumulate_across_the_closure_and_gacela_php(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);
        $this->installed->writeGacelaPhp(<<<'PHP'
            use Gacela\Framework\Bootstrap\GacelaConfig;

            return static function (GacelaConfig $config): void {
                $config->dontDiscover(['gacela-fixture/legacy-numbering']);
            };
            PHP);

        $this->bootstrap(static function (GacelaConfig $config): void {
            $config->dontDiscover(['gacela-fixture/audit-trail']);
        });

        self::assertSame([], $this->discoveredNames());
    }

    public function test_a_declared_config_that_is_not_there_does_not_stop_the_boot(): void
    {
        $this->installed->install(['MissingConfig', 'AuditTrail']);

        $this->bootstrap();

        self::assertSame(['gacela-fixture/audit-trail'], $this->discoveredNames());
        self::assertSame([PackageRefusal::MissingFile], $this->refusalReasons());
    }

    public function test_a_config_that_does_not_return_a_callable_does_not_stop_the_boot(): void
    {
        $this->installed->install(['NotCallable', 'AuditTrail']);

        $this->bootstrap();

        self::assertSame(['gacela-fixture/audit-trail'], $this->discoveredNames());
        self::assertSame([PackageRefusal::NotCallable], $this->refusalReasons());
    }

    /**
     * The merge order, seen from a listener the application registered -- which
     * only works because the event is dispatched once the whole configuration is
     * assembled.
     */
    public function test_the_merge_order_is_observable_from_the_projects_own_listener(): void
    {
        $this->installed->install(['AuditTrail', 'LegacyNumbering']);
        $this->installed->writeGacelaPhp(<<<'PHP'
            use Gacela\Framework\Bootstrap\GacelaConfig;
            use Gacela\Framework\Event\Bootstrap\PackageConfigMergedEvent;
            use GacelaTest\Feature\Framework\PackageDiscovery\App\BootLog;

            return static function (GacelaConfig $config): void {
                $config->registerSpecificListener(
                    PackageConfigMergedEvent::class,
                    static fn (PackageConfigMergedEvent $event): null => BootLog::recordMerge($event),
                );
            };
            PHP);

        $this->bootstrap();

        self::assertSame(
            ['1 gacela-fixture/audit-trail', '2 gacela-fixture/legacy-numbering'],
            BootLog::lines(),
        );
    }

    public function test_the_resolved_list_is_written_to_the_cache_directory(): void
    {
        $this->installed->install(['AuditTrail']);
        $cacheDir = $this->installed->cacheDir();
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . PackageConfigCache::FILENAME;
        $this->installed->alsoRemove($cacheFile);

        $this->bootstrap(static function (GacelaConfig $config) use ($cacheDir): void {
            $config->enableFileCache($cacheDir);
        });

        self::assertTrue(is_file($cacheFile), 'the resolved list was not cached');

        $cached = (array) require $cacheFile;

        self::assertSame(
            [['name' => 'gacela-fixture/audit-trail', 'declaredPath' => 'config/gacela.php', 'configFile' => $this->auditTrailConfigFile()]],
            $cached['packages'],
        );
    }

    /**
     * An application root that Composer never installed against.
     */
    public function test_without_installed_json_nothing_is_discovered_and_nothing_complains(): void
    {
        $this->bootstrap();

        self::assertSame([], $this->discoveredNames());
        self::assertSame([], PackageDiscoveryRegistry::refused());
        self::assertFalse(PackageDiscoveryRegistry::isDisabled());
    }

    private function bootstrap(?callable $extra = null): void
    {
        Gacela::bootstrap($this->installed->appRoot, static function (GacelaConfig $config) use ($extra): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);

            if ($extra !== null) {
                $extra($config);
            }
        });
    }

    /**
     * @return list<string>
     */
    private function discoveredNames(): array
    {
        return array_map(
            static fn (object $package): string => (string) $package->name,
            PackageDiscoveryRegistry::discovered(),
        );
    }

    /**
     * @return list<PackageRefusal>
     */
    private function refusalReasons(): array
    {
        return array_map(
            static fn (object $package): PackageRefusal => $package->reason,
            PackageDiscoveryRegistry::refused(),
        );
    }

    private function auditTrailConfigFile(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'Packages' . DIRECTORY_SEPARATOR . 'AuditTrail'
            . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'gacela.php';
    }
}
