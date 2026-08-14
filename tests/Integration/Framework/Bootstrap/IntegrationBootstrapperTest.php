<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\IntegrationBootstrapper;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * The two behaviours that are the reason this is in core rather than copied
 * into each bridge, driven by a plain closure so no framework is involved.
 *
 * Everything else it does -- the cache options, the type-key binding rule -- is
 * covered through the adapters in each bridge's own `GacelaBootstrapperTest`.
 */
final class IntegrationBootstrapperTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
    }

    /**
     * A bridge that instantiated the entity manager by the act of configuring
     * it would cost more than it saves, so the host is asked for a service only
     * when something reaches for it.
     */
    public function test_a_listed_service_is_not_resolved_by_bootstrapping(): void
    {
        $resolved = 0;

        $this->bootstrap([HostService::class => 'host.service'], static function (string $id) use (&$resolved): object {
            ++$resolved;

            return new HostService($id);
        });

        self::assertSame(0, $resolved, 'bootstrapping asked the host for a service');

        Gacela::get(HostService::class);

        self::assertSame(1, $resolved);
    }

    /**
     * The line an integration is most likely not to know it needs.
     *
     * A host that boots twice in one process keeps the previous boot's locator
     * without it, and that locator keeps serving the previous boot's container
     * -- so the second boot's configuration is registered and ignored.
     */
    public function test_a_second_bootstrap_replaces_the_first_configuration(): void
    {
        $this->bootstrap(
            [HostService::class => 'first'],
            static fn (string $id): object => new HostService($id),
        );

        self::assertSame('first', Gacela::get(HostService::class)?->id);

        $this->bootstrap(
            [HostService::class => 'second'],
            static fn (string $id): object => new HostService($id),
        );

        self::assertSame('second', Gacela::get(HostService::class)?->id);
    }

    /**
     * @param array<string, string> $externalServices
     * @param callable(string): object $resolveService
     */
    private function bootstrap(array $externalServices, callable $resolveService): void
    {
        (new IntegrationBootstrapper(
            __DIR__,
            ['cache_dir' => null, 'file_cache' => false, 'project_namespaces' => []],
            $resolveService(...),
            $externalServices,
        ))->bootstrap();
    }
}

final class HostService
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
