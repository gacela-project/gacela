<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Psr\Container\ContainerInterface;

use function class_exists;
use function interface_exists;

/**
 * Bootstraps Gacela from what the Laravel application already knows.
 *
 * Every Laravel project that uses Gacela writes this by hand -- the base path,
 * the handful of Laravel services a Factory needs to reach. It is the same
 * code every time, which is what a bridge is for.
 *
 * Package tests build fresh applications constantly, so bootstrapping is
 * idempotent by being repeated: each boot re-runs it, and the latest
 * configuration is the one in force.
 */
final class GacelaBootstrapper
{
    /**
     * @param array{cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>} $options
     * @param array<string, string> $externalServices gacela key => laravel binding id
     */
    public function __construct(
        private readonly string $appRootDir,
        private readonly array $options,
        private readonly ContainerInterface $services,
        private readonly array $externalServices = [],
    ) {
    }

    public function bootstrap(): void
    {
        Gacela::bootstrap($this->appRootDir, function (GacelaConfig $config): void {
            // Without this, a re-bootstrap keeps the previous boot's locator,
            // which keeps serving the previous boot's container -- the #597
            // class of bug, one layer down. A first boot resets nothing but
            // empty caches, so it only costs where it is needed.
            $config->resetInMemoryCache();

            $this->applyOptions($config);
            $this->applyExternalServices($config);
        });
    }

    private function applyOptions(GacelaConfig $config): void
    {
        if ($this->options['file_cache'] !== null) {
            $config->setFileCache($this->options['file_cache'], $this->options['cache_dir']);
        } elseif ($this->options['cache_dir'] !== null) {
            $config->enableFileCache($this->options['cache_dir']);
        }

        if ($this->options['project_namespaces'] !== []) {
            $config->setProjectNamespaces($this->options['project_namespaces']);
        }
    }

    /**
     * A listed service reaches Gacela two ways, and which ones apply is decided
     * by the key the project chose.
     *
     * Every key becomes an external service: that is what a project's own
     * `gacela.php` reads through `getExternalService()` when it declares its
     * bindings. A key that *names a type* additionally becomes a binding, so
     * `LoggerInterface::class => 'log'` is resolvable on its own -- by
     * `Gacela::get()`, by autowiring, by `#[Inject]`. Bindings map types to
     * implementations, so a key like `logger` has no business being one.
     *
     * Both take a closure, so a Laravel binding reaches Gacela without being
     * constructed by the act of configuring it: a bridge that instantiated the
     * database connection on every boot would cost more than it saves.
     */
    private function applyExternalServices(GacelaConfig $config): void
    {
        foreach ($this->externalServices as $key => $serviceId) {
            $factory = fn (): object => $this->service($serviceId);

            $config->addExternalService($key, $factory);

            if (class_exists($key) || interface_exists($key)) {
                $config->addBinding($key, $factory);
            }
        }
    }

    private function service(string $serviceId): object
    {
        /** @var object $service */
        $service = $this->services->get($serviceId);

        return $service;
    }
}
