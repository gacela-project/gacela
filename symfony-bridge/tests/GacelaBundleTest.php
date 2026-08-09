<?php

declare(strict_types=1);

namespace GacelaTest\SymfonyBridge;

use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use Gacela\SymfonyBridge\DependencyInjection\GacelaExtension;
use GacelaTest\SymfonyBridge\Fixtures\CountingService;
use GacelaTest\SymfonyBridge\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;

/**
 * The bundle driven through a real kernel, because everything it does happens
 * during compilation or boot -- neither of which a unit test can stand in for.
 */
final class GacelaBundleTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        CountingService::$constructed = 0;
    }

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

    public function test_a_listed_symfony_service_is_reachable_from_gacela(): void
    {
        $this->kernelWithCountingService()->boot();

        $service = Gacela::get(CountingService::class);

        self::assertInstanceOf(CountingService::class, $service);
        self::assertSame('counting', $service->name());
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
