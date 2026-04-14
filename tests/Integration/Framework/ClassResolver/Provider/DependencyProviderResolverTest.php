<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\Provider\DependencyProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Feature\Framework\ExtendService\Module\Facade as LegacyFacade;
use GacelaTest\Feature\Framework\ModuleWithExternalDependencies\Supplier\Facade as ProviderFacade;
use PHPUnit\Framework\TestCase;

use const E_USER_DEPRECATED;

final class DependencyProviderResolverTest extends TestCase
{
    /** @var list<string> */
    private array $capturedDeprecations = [];

    protected function setUp(): void
    {
        $this->capturedDeprecations = [];
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();

        set_error_handler(
            function (int $errno, string $message): bool {
                if ($errno === E_USER_DEPRECATED) {
                    $this->capturedDeprecations[] = $message;
                    return true;
                }

                return false;
            },
            E_USER_DEPRECATED,
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        Config::resetInstance();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();
    }

    public function test_resolves_legacy_dependency_provider_and_triggers_deprecation(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        // Use a caller whose module exposes an AbstractDependencyProvider subclass.
        $caller = new LegacyFacade();
        $resolved = (new DependencyProviderResolver())->resolve($caller);

        self::assertNotNull($resolved, 'resolver must return the legacy provider');
        self::assertCount(1, $this->capturedDeprecations, 'exactly one deprecation must fire for AbstractDependencyProvider usage');
        self::assertStringContainsString('AbstractDependencyProvider', $this->capturedDeprecations[0]);
        self::assertStringContainsString('AbstractProvider', $this->capturedDeprecations[0]);
        self::assertStringContainsString('Module', $this->capturedDeprecations[0], 'deprecation message must mention the caller module name');
    }

    public function test_resolves_new_provider_without_triggering_deprecation(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });

        // This caller uses AbstractProvider (new style), not AbstractDependencyProvider.
        $caller = new ProviderFacade();
        (new DependencyProviderResolver())->resolve($caller);

        self::assertSame([], $this->capturedDeprecations, 'no deprecation should fire when the module uses AbstractProvider');
    }
}
