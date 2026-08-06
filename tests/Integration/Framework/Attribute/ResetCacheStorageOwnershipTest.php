<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Attribute;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Attribute\CacheStorageInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

/**
 * `Gacela::resetCache()` is an in-memory reset. It must not reach a backend the
 * application registered with `CacheableConfig::setStorage()`, because that one
 * is typically APCu or Redis and is shared with the rest of the application.
 */
final class ResetCacheStorageOwnershipTest extends TestCase
{
    protected function tearDown(): void
    {
        CacheableConfig::reset();
    }

    public function test_reset_cache_leaves_a_user_supplied_storage_untouched(): void
    {
        $storage = $this->createMock(CacheStorageInterface::class);
        $storage->expects(self::never())->method('clear');

        CacheableConfig::setStorage($storage);

        Gacela::resetCache();
    }

    public function test_reset_cache_clears_the_default_in_memory_storage(): void
    {
        CacheableConfig::reset();
        CacheableConfig::getStorage()->set('some-key', 'some-value', 60);

        Gacela::resetCache();

        self::assertFalse(CacheableConfig::getStorage()->has('some-key'));
    }
}
