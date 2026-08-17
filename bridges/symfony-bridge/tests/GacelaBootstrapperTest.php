<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\SymfonyBridge\GacelaBootstrapper;
use GacelaTest\SymfonyBridge\Fixtures\CountingService;
use GacelaTest\SymfonyBridge\Fixtures\ServiceContract;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The bootstrapper on its own, where the two options that decide the file cache
 * can be stated one at a time.
 */
final class GacelaBootstrapperTest extends SymfonyBridgeTestCase
{
    private const APP_ROOT = __DIR__ . '/Fixtures';

    public function test_it_bootstraps_from_the_given_application_root(): void
    {
        $this->bootstrap();

        self::assertSame(self::APP_ROOT, Config::getInstance()->getAppRootDir());
    }

    /**
     * A cache directory with nothing said about the file cache means the
     * project wants the cache, in that directory.
     */
    public function test_a_cache_dir_alone_turns_the_file_cache_on(): void
    {
        $this->bootstrap(['cache_dir' => self::APP_ROOT . '/bootstrapper-cache']);

        self::assertTrue(Config::getInstance()->getSetupGacela()->isFileCacheEnabled());
    }

    public function test_the_file_cache_can_be_turned_off_explicitly(): void
    {
        $this->bootstrap(['cache_dir' => self::APP_ROOT . '/bootstrapper-cache', 'file_cache' => false]);

        self::assertFalse(Config::getInstance()->getSetupGacela()->isFileCacheEnabled());
    }

    /**
     * The explicit-on path is its own branch, and "off is the default anyway"
     * would let it rot unnoticed: turning it on must both enable the cache and
     * carry the directory along.
     */
    public function test_the_file_cache_can_be_turned_on_explicitly(): void
    {
        $cacheDir = self::APP_ROOT . '/bootstrapper-cache';
        $this->bootstrap(['cache_dir' => $cacheDir, 'file_cache' => true]);

        self::assertTrue(Config::getInstance()->getSetupGacela()->isFileCacheEnabled());
        self::assertSame($cacheDir, Config::getInstance()->getSetupGacela()->getFileCacheDirectory());
    }

    /**
     * Saying nothing must change nothing: the bundle's defaults are Gacela's
     * defaults, not a second opinion about them.
     */
    public function test_saying_nothing_leaves_gacelas_own_default_in_place(): void
    {
        $this->bootstrap();

        self::assertSame('', Config::getInstance()->getSetupGacela()->getFileCacheDirectory());
    }

    public function test_project_namespaces_are_passed_through(): void
    {
        $this->bootstrap(['project_namespaces' => ['App']]);

        self::assertSame(['App'], Config::getInstance()->getSetupGacela()->getProjectNamespaces());
    }

    /**
     * A key that names a type becomes a binding, so it resolves on its own --
     * which is what `#[Inject]` and autowiring go through.
     */
    public function test_a_service_listed_under_its_type_is_bound(): void
    {
        $this->bootstrap([], [CountingService::class => 'app.counting']);

        self::assertInstanceOf(CountingService::class, Gacela::get(CountingService::class));
    }

    /**
     * An interface names a type as much as a class does.
     */
    public function test_a_service_listed_under_an_interface_is_bound(): void
    {
        $this->bootstrap([], [ServiceContract::class => 'app.counting']);

        self::assertInstanceOf(CountingService::class, Gacela::get(ServiceContract::class));
    }

    /**
     * A key that names no type is still an external service, for a `gacela.php`
     * to turn into whatever binding it wants -- bindings map types, and
     * `counting` is not one.
     */
    public function test_a_service_listed_under_a_plain_key_is_not_bound(): void
    {
        $this->bootstrap([], ['counting' => 'app.counting']);

        self::assertNull(Gacela::get('counting'));
        self::assertArrayHasKey('counting', Config::getInstance()->getSetupGacela()->externalServices());
    }

    /**
     * @param array{cache_dir?: ?string, file_cache?: ?bool, project_namespaces?: list<string>} $options
     * @param array<string, string> $externalServices
     */
    private function bootstrap(array $options = [], array $externalServices = []): void
    {
        $bootstrapper = new GacelaBootstrapper(
            self::APP_ROOT,
            [
                'cache_dir' => $options['cache_dir'] ?? null,
                'file_cache' => $options['file_cache'] ?? null,
                'project_namespaces' => $options['project_namespaces'] ?? [],
            ],
            $this->services(),
            $externalServices,
        );

        $bootstrapper->bootstrap();
    }

    private function services(): ContainerInterface
    {
        return new ServiceLocator([
            'app.counting' => static fn (): CountingService => new CountingService(),
        ]);
    }
}
