<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Attribute;

use Gacela\Framework\Attribute\InMemoryCacheStorage;
use PHPUnit\Framework\TestCase;

use function sleep;

final class InMemoryCacheStorageTest extends TestCase
{
    /**
     * Zero means "no expiry", which is what `FileCache` has always documented
     * and implemented -- its own default TTL is 0. This storage used to write
     * `time() + 0` instead, so an entry stored with `ttl: 0` was already
     * expired before the call returned, and every read missed.
     */
    public function test_a_zero_ttl_stores_the_entry_without_expiry(): void
    {
        $storage = new InMemoryCacheStorage();
        $storage->set('key', 'value', 0);

        self::assertTrue($storage->has('key'));
        self::assertSame('value', $storage->get('key'));
    }

    /**
     * Negative is supported rather than rejected: the entry is already expired
     * when written. `FileCache` behaves the same way, and its own tests rely on
     * it to exercise eviction without sleeping.
     */
    public function test_a_negative_ttl_is_already_expired(): void
    {
        $storage = new InMemoryCacheStorage();
        $storage->set('key', 'value', -1);

        self::assertFalse($storage->has('key'));
        self::assertSame('fallback', $storage->get('key', 'fallback'));
    }

    public function test_a_positive_ttl_keeps_the_entry_until_it_elapses(): void
    {
        $storage = new InMemoryCacheStorage();
        $storage->set('key', 'value', 3600);

        self::assertTrue($storage->has('key'));
        self::assertSame('value', $storage->get('key'));
    }

    /**
     * Both halves in one wait: a one-second entry expires, and the zero-TTL one
     * stored alongside it does not. Asserting "no expiry" without something
     * that *does* expire in the same run would only be untested optimism.
     */
    public function test_a_one_second_ttl_expires_while_a_zero_ttl_entry_survives(): void
    {
        $storage = new InMemoryCacheStorage();
        $storage->set('brief', 'value', 1);
        $storage->set('forever', 'value', 0);

        self::assertTrue($storage->has('brief'));

        sleep(2);

        self::assertFalse($storage->has('brief'));
        self::assertSame('fallback', $storage->get('brief', 'fallback'));
        self::assertTrue($storage->has('forever'));
    }
}
