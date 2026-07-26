<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ServiceResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Gacela;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeAttributeCommand;
use GacelaTest\Integration\Framework\ServiceResolver\Module\FakeCommand;
use PHPUnit\Framework\TestCase;

use function restore_error_handler;
use function set_error_handler;

use const E_USER_DEPRECATED;

/**
 * Resolving a pillar from a `@method` docblock, or by scanning the caller's
 * `use` statements, is deprecated in 2.0 and removed in 3.0. `#[ServiceMap]`
 * is the replacement.
 *
 * Without this test the deprecation is invisible: it fires on a **cold resolve
 * only**, because the answer is memoized per caller-and-method. A warm cache
 * stays silent, which is right for production and useless as a guard — so the
 * cache is reset here deliberately.
 */
final class DocBlockFallbackDeprecationTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetGacela();
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setFileCache(false);
        });
    }

    protected function tearDown(): void
    {
        $this->resetGacela();
    }

    public function test_resolving_from_a_docblock_raises_a_deprecation(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeCommand())->getFacade();
        });

        self::assertCount(1, $notices);
        self::assertStringContainsString('FakeCommand::getFacade()', $notices[0]);
        self::assertStringContainsString('#[ServiceMap(', $notices[0]);
    }

    public function test_declaring_the_pillar_with_the_attribute_raises_nothing(): void
    {
        $notices = $this->capturingDeprecations(static function (): void {
            (new FakeAttributeCommand())->getFacade();
        });

        self::assertSame([], $notices, 'the attribute is the primary path and must stay silent');
    }

    /**
     * @param callable():void $body
     *
     * @return list<string>
     */
    private function capturingDeprecations(callable $body): array
    {
        $notices = [];

        set_error_handler(
            static function (int $severity, string $message) use (&$notices): bool {
                $notices[] = $message;

                return true;
            },
            E_USER_DEPRECATED,
        );

        try {
            $body();
        } finally {
            restore_error_handler();
        }

        return $notices;
    }

    private function resetGacela(): void
    {
        Gacela::resetCache();
        Config::resetInstance();
        InMemoryCache::resetCache();
    }
}
