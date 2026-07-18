<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Bootstrap;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BootstrapBatchesFileCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->resetInMemoryCache();
            $config->setFileCache(false);
        });
    }

    public function test_bootstrap_batches_file_cache_writes_while_resolving_classes(): void
    {
        $batchingWhileResolving = null;

        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config) use (&$batchingWhileResolving): void {
            $config->resetInMemoryCache();
            // Plugins run during bootstrap's resolution phase; capture whether
            // file-cache writes are being batched at that point.
            $config->addPlugin(static function () use (&$batchingWhileResolving): void {
                $batchingWhileResolving = AbstractPhpFileCache::isBatching();
            });
        });

        self::assertTrue(
            $batchingWhileResolving,
            'file-cache writes must be batched while bootstrap resolves classes',
        );
        self::assertFalse(
            AbstractPhpFileCache::isBatching(),
            'the bootstrap batch must be committed before bootstrap() returns',
        );
    }

    public function test_bootstrap_commits_file_cache_batch_even_when_a_plugin_throws(): void
    {
        try {
            Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
                $config->resetInMemoryCache();
                $config->addPlugin(static function (): void {
                    throw new RuntimeException('plugin boom');
                });
            });
            self::fail('the throwing plugin should abort bootstrap');
        } catch (RuntimeException) {
            // expected
        }

        self::assertFalse(
            AbstractPhpFileCache::isBatching(),
            'a failed bootstrap must still commit (close) the file-cache batch',
        );
    }
}
