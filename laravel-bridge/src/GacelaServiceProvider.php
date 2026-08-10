<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Console\Infrastructure\Command\CacheClearCommand;
use Gacela\Console\Infrastructure\Command\CacheWarmCommand;
use Gacela\Console\Infrastructure\Command\InitCommand;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use Override;
use Symfony\Component\Console\Command\Command;

/**
 * Gacela inside a Laravel application.
 *
 * Register it and the four things every Laravel/Gacela project wires by hand
 * are done: bootstrapping when the application boots, mapping Laravel services
 * into Gacela, the artisan commands, and warming the caches with Laravel's own
 * `artisan optimize`. `#[Inject]` on Laravel-resolved services is honored
 * through {@see GacelaInjectListener} and {@see Attribute\Inject}.
 *
 * ```php
 * // bootstrap/providers.php
 * return [
 *     Gacela\LaravelBridge\GacelaServiceProvider::class,
 * ];
 * ```
 *
 * @psalm-import-type GacelaBridgeConfig from Configuration
 */
final class GacelaServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(Configuration::DEFAULTS_FILE, 'gacela');
    }

    /**
     * Every boot bootstraps again, on purpose: package tests build fresh
     * applications constantly, and the latest application's configuration is
     * the one that should be in force -- not whatever an earlier one left
     * behind.
     */
    public function boot(): void
    {
        $config = Configuration::validate($this->gacelaConfig());

        if ($this->app->runningInConsole()) {
            $this->publishes([
                Configuration::DEFAULTS_FILE => $this->app->configPath('gacela.php'),
            ], 'gacela-config');
        }

        if (!$config['enabled']) {
            return;
        }

        $appRootDir = $config['app_root_dir'] ?? $this->app->basePath();

        (new GacelaBootstrapper(
            $appRootDir,
            [
                'cache_dir' => $config['cache_dir'],
                'file_cache' => $config['file_cache'],
                'project_namespaces' => $config['project_namespaces'],
            ],
            $this->app,
            $config['external_services'],
        ))->bootstrap();

        GacelaInjectListener::register($this->app);

        if ($config['register_commands']) {
            $this->registerGacelaCommands($config['command_prefix'], $appRootDir);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function gacelaConfig(): array
    {
        /** @var Repository $repository */
        $repository = $this->app->make('config');

        return (array)$repository->get('gacela', []);
    }

    /**
     * The names carry a prefix, because artisan owns `make:*` -- the same
     * collision MakerBundle forces on the Symfony bundle. Each class is bound
     * as a singleton building the renamed command, so artisan's own
     * resolution hands back the prefixed one.
     *
     * `optimize` is hooked inside this guard: hooking it while the commands
     * are not registered would point `artisan optimize` at a command that
     * does not exist.
     */
    private function registerGacelaCommands(string $prefix, string $appRootDir): void
    {
        $names = GacelaCommands::names();

        foreach ($names as $class => $name) {
            $prefixed = $prefix . $name;
            $this->app->singleton($class, static function () use ($class, $prefixed, $appRootDir): Command {
                $command = $class === InitCommand::class ? new InitCommand($appRootDir) : new $class();
                $command->setName($prefixed);

                return $command;
            });
        }

        $this->commands(array_keys($names));

        $this->optimizes(
            optimize: $prefix . $names[CacheWarmCommand::class],
            clear: $prefix . $names[CacheClearCommand::class],
            key: 'gacela',
        );
    }
}
