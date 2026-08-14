<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap;

use Closure;
use Gacela\Framework\Gacela;

use function class_exists;
use function interface_exists;

/**
 * Bootstraps Gacela from what a host application already knows.
 *
 * Every project integrating Gacela with another framework writes this by hand:
 * the root directory, the cache settings, and the handful of host services a
 * Factory needs to reach. It is the same code every time, which is what a
 * bridge is for -- and it was the same code in both bridges, byte for byte
 * once the docblocks were stripped.
 *
 * It lives here rather than in either of them because none of it is specific to
 * a framework, and because of the one line that is easy not to know about:
 * `resetInMemoryCache()`. A host that boots twice in one process -- which
 * functional tests do constantly -- otherwise keeps the previous boot's
 * locator, which keeps serving the previous boot's container. Both bridges had
 * to learn that separately (#665, #667). A third integration now gets it
 * without being told.
 *
 * The host is reached through a closure rather than a PSR-11 container so that
 * this carries no dependency an integration might not have, and so an
 * integration whose lookup is not a container can still use it.
 */
final class IntegrationBootstrapper
{
    /**
     * @param array{cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>} $options
     * @param Closure(string): object $resolveService looks a host service up by its id
     * @param array<string, string> $externalServices gacela key => host service id
     */
    public function __construct(
        private readonly string $appRootDir,
        private readonly array $options,
        private readonly Closure $resolveService,
        private readonly array $externalServices = [],
    ) {
    }

    public function bootstrap(): void
    {
        Gacela::bootstrap($this->appRootDir, function (GacelaConfig $config): void {
            // Without this, a re-bootstrap keeps the previous boot's locator,
            // which keeps serving the previous boot's container -- the #597
            // class of bug, one layer down (#666). A first boot resets nothing
            // but empty caches, so it only costs where it is needed.
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
     * `LoggerInterface::class => 'monolog.logger'` is resolvable on its own --
     * by `Gacela::get()`, by autowiring, by `#[Inject]`. Bindings map types to
     * implementations, so a key like `logger` has no business being one.
     *
     * Both take a closure, so a host service reaches Gacela without being
     * constructed by the act of configuring it: a bridge that instantiated the
     * entity manager on every boot would cost more than it saves.
     */
    private function applyExternalServices(GacelaConfig $config): void
    {
        foreach ($this->externalServices as $key => $serviceId) {
            $factory = fn (): object => ($this->resolveService)($serviceId);

            $config->addExternalService($key, $factory);

            if (class_exists($key) || interface_exists($key)) {
                $config->addBinding($key, $factory);
            }
        }
    }
}
