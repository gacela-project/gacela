<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Warms Gacela's caches from Symfony's own `cache:warmup`.
 *
 * A Gacela deployment has two warmup steps otherwise, and the second one is
 * the one people forget. This is the same command `vendor/bin/gacela
 * cache:warm` runs, so "warmed" means the same thing either way -- including
 * writing nothing at all when the file cache is disabled.
 */
final class GacelaCacheWarmer implements CacheWarmerInterface
{
    /**
     * Optional: a project that warms nothing still boots, just colder.
     */
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @return list<string> the classes to preload, which Gacela's own caches
     *                      are not: they are php files read at runtime, not
     *                      opcache preload candidates
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        (new CacheWarmCommand())->run(new ArrayInput([]), new NullOutput());

        return [];
    }
}
