<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\Provider;

use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\Provider\DependencyProviderResolver;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ClassResolver\Provider\LegacyModule\DependencyProvider;
use GacelaTest\Integration\Framework\ClassResolver\Provider\LegacyModule\Factory as LegacyFactory;
use GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule\Factory as ModernFactory;
use GacelaTest\Integration\Framework\ClassResolver\Provider\ModernModule\Provider;
use PHPUnit\Framework\TestCase;

use function sprintf;

use const E_USER_DEPRECATED;

final class ProviderRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGacela();

        Provider::resetCallCount();
        DependencyProvider::resetCallCount();

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        $this->resetGacela();

        Provider::resetCallCount();
        DependencyProvider::resetCallCount();
    }

    /**
     * `ProviderResolver` and `DependencyProviderResolver` share one normalized cache
     * slot ("DependencyProvider" -> "Provider"), so a modern provider is returned by
     * both. It must still be registered only once, otherwise every non-idempotent
     * provider body runs its side effects twice.
     */
    public function test_modern_provider_is_registered_exactly_once(): void
    {
        (new ModernFactory())->getGreeting();

        self::assertSame(1, Provider::$provideCallCount);
    }

    public function test_modern_provider_provides_its_dependencies(): void
    {
        self::assertSame('hello from the modern provider', (new ModernFactory())->getGreeting());
    }

    public function test_legacy_dependency_provider_is_registered_exactly_once(): void
    {
        (new LegacyFactory())->getGreeting();

        self::assertSame(1, DependencyProvider::$provideCallCount);
    }

    public function test_legacy_dependency_provider_still_provides_its_dependencies(): void
    {
        self::assertSame(
            'hello from the legacy dependency provider',
            (new LegacyFactory())->getGreeting(),
        );
    }

    /**
     * The notice is emitted without symfony/deprecation-contracts installed, but keeps that
     * contract's "Since <package> <version>: " format so deprecation collectors group it
     * unchanged. Locked here because the format is the part consumers' tooling depends on.
     */
    public function test_legacy_dependency_provider_triggers_a_deprecation_in_the_contract_format(): void
    {
        $captured = [];
        set_error_handler(
            static function (int $errno, string $message) use (&$captured): bool {
                $captured[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            (new DependencyProviderResolver())->resolve(new LegacyFactory());
        } finally {
            restore_error_handler();
        }

        self::assertSame([sprintf(
            "Since gacela-project/gacela 1.8: `%s` is deprecated and will be removed in version 2.0.\n"
            . 'Use `%s` instead. Where? Check your module `LegacyModule`',
            AbstractDependencyProvider::class,
            AbstractProvider::class,
        )], $captured);
    }

    private function resetGacela(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        InMemoryCache::resetCache();
    }
}
