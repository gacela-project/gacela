<?php

declare(strict_types=1);

namespace GacelaTest\LaravelBridge\Fixtures;

use Gacela\LaravelBridge\GacelaServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;

use function bin2hex;
use function dirname;
use function random_bytes;
use function sys_get_temp_dir;

/**
 * The smallest container that can run a provider: no laravel/framework, so
 * what a test observes is the bridge and nothing else. The three methods the
 * provider reaches on a real Application are answered here.
 */
final class TestApplication extends Container
{
    private readonly string $id;

    /**
     * @param array<string, mixed> $gacelaConfig what `config/gacela.php` would say
     */
    public function __construct(
        array $gacelaConfig = [],
        private readonly bool $runningInConsole = true,
    ) {
        $this->id = bin2hex(random_bytes(6));

        $this->instance('config', new Repository(['gacela' => $gacelaConfig]));
    }

    public function boot(): void
    {
        $provider = new GacelaServiceProvider($this);
        $provider->register();
        $provider->boot();
    }

    public function basePath(string $path = ''): string
    {
        return dirname(__DIR__) . ($path === '' ? '' : DIRECTORY_SEPARATOR . $path);
    }

    public function configPath(string $path = ''): string
    {
        return sys_get_temp_dir() . '/gacela-bridge-app/' . $this->id . '/config'
            . ($path === '' ? '' : DIRECTORY_SEPARATOR . $path);
    }

    public function runningInConsole(): bool
    {
        return $this->runningInConsole;
    }
}
