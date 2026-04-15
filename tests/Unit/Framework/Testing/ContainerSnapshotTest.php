<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\Testing;

use Gacela\Framework\Testing\ContainerSnapshot;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ContainerSnapshotTest extends TestCase
{
    public function test_it_exposes_constructor_arguments(): void
    {
        $snapshot = new ContainerSnapshot(
            inMemoryCache: ['key' => ['ClassA' => 'ResolvedA']],
            config: ['db.dsn' => 'sqlite::memory:'],
            appRootDir: '/var/app',
            cacheDir: '/var/app/cache',
            extras: ['custom' => 'value'],
        );

        self::assertSame(['key' => ['ClassA' => 'ResolvedA']], $snapshot->inMemoryCache());
        self::assertSame(['db.dsn' => 'sqlite::memory:'], $snapshot->config());
        self::assertSame('/var/app', $snapshot->appRootDir());
        self::assertSame('/var/app/cache', $snapshot->cacheDir());
        self::assertSame(['custom' => 'value'], $snapshot->extras());
    }

    public function test_it_defaults_to_empty_state(): void
    {
        $snapshot = new ContainerSnapshot();

        self::assertSame([], $snapshot->inMemoryCache());
        self::assertSame([], $snapshot->config());
        self::assertNull($snapshot->appRootDir());
        self::assertNull($snapshot->cacheDir());
        self::assertSame([], $snapshot->extras());
    }

    public function test_it_round_trips_through_serialize_and_unserialize(): void
    {
        $original = new ContainerSnapshot(
            inMemoryCache: ['cacheKey' => ['Foo' => 'Bar']],
            config: ['nested' => ['flag' => true]],
            appRootDir: '/tmp/app',
            cacheDir: '/tmp/app/cache',
            extras: ['version' => 42],
        );

        /** @var ContainerSnapshot $restored */
        $restored = unserialize(serialize($original));

        self::assertInstanceOf(ContainerSnapshot::class, $restored);
        self::assertSame($original->inMemoryCache(), $restored->inMemoryCache());
        self::assertSame($original->config(), $restored->config());
        self::assertSame($original->appRootDir(), $restored->appRootDir());
        self::assertSame($original->cacheDir(), $restored->cacheDir());
        self::assertSame($original->extras(), $restored->extras());
    }

    public function test_serialize_returns_the_full_state_array(): void
    {
        $snapshot = new ContainerSnapshot(
            inMemoryCache: ['k' => ['A' => 'B']],
            config: ['x' => 1],
            appRootDir: '/app',
            cacheDir: '/app/cache',
            extras: ['e' => 'v'],
        );

        self::assertSame([
            'inMemoryCache' => ['k' => ['A' => 'B']],
            'config' => ['x' => 1],
            'appRootDir' => '/app',
            'cacheDir' => '/app/cache',
            'extras' => ['e' => 'v'],
        ], $snapshot->__serialize());
    }

    public function test_unserialize_restores_partial_payload_with_defaults(): void
    {
        $reflection = new ReflectionClass(ContainerSnapshot::class);
        /** @var ContainerSnapshot $snapshot */
        $snapshot = $reflection->newInstanceWithoutConstructor();
        $snapshot->__unserialize([]);

        self::assertSame([], $snapshot->inMemoryCache());
        self::assertSame([], $snapshot->config());
        self::assertNull($snapshot->appRootDir());
        self::assertNull($snapshot->cacheDir());
        self::assertSame([], $snapshot->extras());
    }
}
