<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use ArrayObject;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\SymfonyBridge\DependencyInjection\GacelaExtension;
use Gacela\SymfonyBridge\GacelaBundle;
use Gacela\SymfonyBridge\GacelaInjectCompilerPass;
use GacelaTest\SymfonyBridge\Fixtures\CountingService;
use GacelaTest\SymfonyBridge\Fixtures\InjectedCountingConsumer;
use GacelaTest\SymfonyBridge\Fixtures\TestKernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetterInterface;

use function array_map;

/**
 * The bundle driven through a real kernel, because everything it does happens
 * during compilation or boot -- neither of which a unit test can stand in for.
 */
final class GacelaBundleTest extends SymfonyBridgeTestCase
{
    public function test_booting_the_kernel_bootstraps_gacela(): void
    {
        $kernel = new TestKernel();
        $kernel->boot();

        self::assertSame($kernel->getProjectDir(), Config::getInstance()->getAppRootDir());
    }

    /**
     * Functional tests reboot kernels constantly, and a second bootstrap that
     * served the first one's configuration was a real bug (#597). The bridge
     * must not reintroduce it by bootstrapping only once.
     */
    public function test_a_second_boot_bootstraps_again(): void
    {
        (new TestKernel())->boot();
        self::assertSame([], Config::getInstance()->getSetupGacela()->getProjectNamespaces());

        (new TestKernel(['project_namespaces' => ['App']]))->boot();

        self::assertSame(['App'], Config::getInstance()->getSetupGacela()->getProjectNamespaces());
    }

    /**
     * The sharper variant of the same lesson: the locator memoizes instances,
     * and a re-bootstrap that kept it would keep serving the first kernel's
     * services no matter what the second one listed. Configuration alone
     * cannot see this -- only asking for a *service* can (#666).
     */
    public function test_a_second_boot_serves_the_second_kernels_services(): void
    {
        $this->kernelWithCountingService()->boot();
        self::assertSame(CountingService::FROM_SYMFONY, Gacela::get(CountingService::class)?->name());

        (new TestKernel(
            ['external_services' => [CountingService::class => 'app.counting']],
            ['app.counting' => CountingService::class],
            serviceName: 'rebooted',
        ))->boot();

        self::assertSame('rebooted', Gacela::get(CountingService::class)?->name());
    }

    /**
     * Gacela autowires an unlisted class perfectly happily, so "an instance
     * came back" would pass with the whole mapping deleted. What cannot is a
     * constructor argument only Symfony supplies: an autowired one would carry
     * the default instead.
     */
    public function test_a_service_listed_under_its_type_is_the_one_gacela_hands_back(): void
    {
        $kernel = $this->kernelWithCountingService();
        $kernel->boot();

        $service = Gacela::get(CountingService::class);

        self::assertInstanceOf(CountingService::class, $service);
        self::assertSame(CountingService::FROM_SYMFONY, $service->name());
    }

    /**
     * The other audience, and the one every key reaches: a project's own
     * `gacela.php` reads it through `getExternalService()` when it declares its
     * bindings.
     */
    public function test_a_listed_service_is_offered_to_gacela_php_whatever_its_key(): void
    {
        (new TestKernel(
            ['external_services' => ['counting' => 'app.counting']],
            ['app.counting' => CountingService::class],
        ))->boot();

        self::assertArrayHasKey('counting', Config::getInstance()->getSetupGacela()->externalServices());
    }

    /**
     * Configuring is not using: a bridge that built the entity manager on every
     * boot would cost more than it saves.
     */
    public function test_a_listed_service_is_not_built_until_it_is_asked_for(): void
    {
        $this->kernelWithCountingService()->boot();

        self::assertSame(0, CountingService::$constructed, 'booting alone must not construct it');

        Gacela::get(CountingService::class);

        self::assertSame(1, CountingService::$constructed);
    }

    public function test_a_service_nobody_listed_is_not_bound(): void
    {
        (new TestKernel())->boot();

        self::assertNull(Gacela::get('counting'));
    }

    /**
     * The pass is the bridge's oldest job, and registering it is the whole of
     * what `build()` adds: without it `#[Inject]` on a Symfony-managed class
     * does nothing at all, silently, which is the failure it exists to prevent.
     */
    public function test_it_registers_the_inject_compiler_pass(): void
    {
        $container = new ContainerBuilder();

        (new GacelaBundle())->build($container);

        $passes = $container->getCompiler()->getPassConfig()->getBeforeOptimizationPasses();
        $classes = array_map(static fn (object $pass): string => $pass::class, $passes);

        self::assertContains(GacelaInjectCompilerPass::class, $classes);
    }

    /**
     * The pass rewrites `#[Inject]` to a `gacela.container` service, so the
     * bundle has to register that id too (#908). A test of the pass alone
     * builds its own container and cannot see the id missing.
     */
    public function test_inject_on_a_symfony_service_resolves_through_gacela(): void
    {
        $kernel = new TestKernel(
            ['external_services' => [CountingService::class => 'app.counting']],
            ['app.counting' => CountingService::class],
            argumentlessServices: ['app.consumer' => InjectedCountingConsumer::class],
        );
        $kernel->boot();

        $consumer = $kernel->getContainer()->get('app.consumer');

        self::assertInstanceOf(InjectedCountingConsumer::class, $consumer);
        self::assertSame(CountingService::FROM_SYMFONY, $consumer->counting->name());
    }

    /**
     * What a worker runtime does between two requests: Symfony resets its
     * services, and Gacela drops what the first request built.
     */
    public function test_resetting_the_kernel_services_drops_gacelas_request_state(): void
    {
        $kernel = new TestKernel(resetsServices: true);
        $kernel->boot();

        $first = Gacela::get(ArrayObject::class);
        self::assertSame($first, Gacela::get(ArrayObject::class));

        /** @var ServicesResetterInterface $resetter */
        $resetter = $kernel->getContainer()->get('services_resetter');
        $resetter->reset();

        self::assertNotSame($first, Gacela::get(ArrayObject::class));
    }

    public function test_disabling_the_bundle_registers_nothing(): void
    {
        $kernel = new TestKernel(['enabled' => false]);
        $kernel->boot();

        self::assertFalse($kernel->getContainer()->has(GacelaExtension::BOOTSTRAPPER_ID));
    }

    private function kernelWithCountingService(): TestKernel
    {
        return new TestKernel(
            ['external_services' => [CountingService::class => 'app.counting']],
            ['app.counting' => CountingService::class],
        );
    }
}
