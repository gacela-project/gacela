<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\LaravelBridge\Configuration;
use Gacela\LaravelBridge\GacelaCommands;
use Gacela\LaravelBridge\GacelaServiceProvider;
use GacelaTest\LaravelBridge\Fixtures\CountingService;
use GacelaTest\LaravelBridge\Fixtures\TestApplication;
use Illuminate\Console\Application as Artisan;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The provider driven through a real container, because everything it does
 * happens during registration or boot -- neither of which a unit test can
 * stand in for.
 */
final class GacelaServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        CountingService::$constructed = 0;
        Artisan::forgetBootstrappers();
        ServiceProvider::$optimizeCommands = [];
        ServiceProvider::$optimizeClearCommands = [];
    }

    public function test_booting_the_application_bootstraps_gacela(): void
    {
        $app = new TestApplication();
        $app->boot();

        self::assertSame($app->basePath(), Config::getInstance()->getAppRootDir());
    }

    /**
     * Package tests build fresh applications constantly, and a second
     * bootstrap that served the first one's configuration was a real bug
     * (#597). The bridge must not reintroduce it by bootstrapping only once.
     */
    public function test_a_second_boot_bootstraps_again(): void
    {
        (new TestApplication())->boot();
        self::assertSame([], Config::getInstance()->getSetupGacela()->getProjectNamespaces());

        (new TestApplication(['project_namespaces' => ['App']]))->boot();

        self::assertSame(['App'], Config::getInstance()->getSetupGacela()->getProjectNamespaces());
    }

    /**
     * The sharper variant of the same lesson: the locator memoizes instances,
     * and a re-bootstrap that kept it would keep serving the first
     * application's services no matter what the second one listed.
     */
    public function test_a_second_boot_serves_the_second_applications_services(): void
    {
        $this->appWithCountingService()->boot();
        self::assertSame(CountingService::FROM_LARAVEL, Gacela::get(CountingService::class)?->name());

        $rebooted = new TestApplication([
            'external_services' => [CountingService::class => 'app.counting'],
        ]);
        $rebooted->singleton('app.counting', static fn (): CountingService => new CountingService('rebooted'));
        $rebooted->boot();

        self::assertSame('rebooted', Gacela::get(CountingService::class)?->name());
    }

    /**
     * Gacela autowires an unlisted class perfectly happily, so "an instance
     * came back" would pass with the whole mapping deleted. What cannot is a
     * constructor argument only Laravel supplies: an autowired one would carry
     * the default instead.
     */
    public function test_a_service_listed_under_its_type_is_the_one_gacela_hands_back(): void
    {
        $app = $this->appWithCountingService();
        $app->boot();

        $service = Gacela::get(CountingService::class);

        self::assertInstanceOf(CountingService::class, $service);
        self::assertSame(CountingService::FROM_LARAVEL, $service->name());
    }

    /**
     * The other audience, and the one every key reaches: a project's own
     * `gacela.php` reads it through `getExternalService()` when it declares
     * its bindings.
     */
    public function test_a_listed_service_is_offered_to_gacela_php_whatever_its_key(): void
    {
        $app = new TestApplication(['external_services' => ['counting' => 'app.counting']]);
        $app->singleton('app.counting', static fn (): CountingService => new CountingService(CountingService::FROM_LARAVEL));
        $app->boot();

        self::assertArrayHasKey('counting', Config::getInstance()->getSetupGacela()->externalServices());
    }

    /**
     * Configuring is not using: a bridge that built the database connection on
     * every boot would cost more than it saves.
     */
    public function test_a_listed_service_is_not_built_until_it_is_asked_for(): void
    {
        $this->appWithCountingService()->boot();

        self::assertSame(0, CountingService::$constructed, 'booting alone must not construct it');

        Gacela::get(CountingService::class);

        self::assertSame(1, CountingService::$constructed);
    }

    public function test_a_service_nobody_listed_is_not_bound(): void
    {
        (new TestApplication())->boot();

        self::assertNull(Gacela::get('counting'));
    }

    /**
     * `register()` is where the defaults land in the repository, so a project
     * reading `config('gacela.command_prefix')` sees one even before boot.
     */
    public function test_registering_merges_the_default_config(): void
    {
        $app = new TestApplication();
        (new GacelaServiceProvider($app))->register();

        /** @var \Illuminate\Config\Repository $config */
        $config = $app->make('config');

        self::assertSame('gacela:', $config->get('gacela.command_prefix'));
    }

    public function test_the_app_root_dir_is_the_projects_to_choose(): void
    {
        $app = new TestApplication(['app_root_dir' => __DIR__ . '/Fixtures']);
        $app->boot();

        self::assertSame(__DIR__ . '/Fixtures', Config::getInstance()->getAppRootDir());
    }

    public function test_an_unknown_config_key_fails_boot_naming_the_key(): void
    {
        $app = new TestApplication(['cache_dri' => '/tmp/gacela']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cache_dri');

        $app->boot();
    }

    public function test_the_config_is_publishable(): void
    {
        (new TestApplication())->boot();

        $paths = ServiceProvider::pathsToPublish(GacelaServiceProvider::class, 'gacela-config');

        self::assertArrayHasKey(Configuration::DEFAULTS_FILE, $paths);
    }

    public function test_artisan_lists_the_commands_under_the_gacela_prefix(): void
    {
        $app = new TestApplication();
        $app->boot();

        $artisan = $this->artisan($app);

        foreach (GacelaCommands::names() as $name) {
            self::assertTrue($artisan->has('gacela:' . $name), 'missing gacela:' . $name);
        }

        self::assertFalse($artisan->has('make:module'), 'artisan owns make:*; the bare name must stay free');
    }

    public function test_the_prefix_is_the_projects_to_choose(): void
    {
        $app = new TestApplication(['command_prefix' => 'g:']);
        $app->boot();

        self::assertTrue($this->artisan($app)->has('g:make:module'));
    }

    public function test_optimize_warms_and_clears_through_the_gacela_commands(): void
    {
        (new TestApplication())->boot();

        self::assertSame('gacela:cache:warm', ServiceProvider::$optimizeCommands['gacela'] ?? null);
        self::assertSame('gacela:cache:clear', ServiceProvider::$optimizeClearCommands['gacela'] ?? null);
    }

    /**
     * names() constructs all fifteen commands to read their names -- a price
     * no web request should pay for artisan commands it can never run.
     */
    public function test_a_web_request_registers_no_commands(): void
    {
        $app = new TestApplication([], runningInConsole: false);
        $app->boot();

        self::assertFalse($this->artisan($app)->has('gacela:make:module'));
        self::assertArrayNotHasKey('gacela', ServiceProvider::$optimizeCommands);
    }

    public function test_registering_no_commands_registers_no_optimize_hooks_either(): void
    {
        $app = new TestApplication(['register_commands' => false]);
        $app->boot();

        self::assertFalse($this->artisan($app)->has('gacela:make:module'));
        self::assertArrayNotHasKey('gacela', ServiceProvider::$optimizeCommands);
    }

    public function test_disabling_the_bridge_registers_nothing(): void
    {
        $app = new TestApplication([
            'enabled' => false,
            'external_services' => ['counting' => 'app.counting'],
        ]);
        $app->boot();

        self::assertFalse($this->artisan($app)->has('gacela:make:module'));
        self::assertArrayNotHasKey('gacela', ServiceProvider::$optimizeCommands);
    }

    private function appWithCountingService(): TestApplication
    {
        $app = new TestApplication([
            'external_services' => [CountingService::class => 'app.counting'],
        ]);
        $app->singleton('app.counting', static fn (): CountingService => new CountingService(CountingService::FROM_LARAVEL));

        return $app;
    }

    private function artisan(TestApplication $app): Artisan
    {
        return new Artisan($app, new Dispatcher($app), 'testing');
    }
}
