<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Gacela;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps Gacela from what the Symfony kernel already knows.
 *
 * Every Symfony project that uses Gacela writes this by hand -- the project
 * dir, the environment, the handful of Symfony services a Factory needs to
 * reach. It is the same code every time, which is what a bridge is for.
 *
 * A kernel can boot more than once in one process (functional tests do it
 * constantly), so bootstrapping is idempotent by being repeated: each boot
 * re-runs it, and the latest configuration is the one in force.
 */
final class GacelaBootstrapper
{
    /**
     * @param array{cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>} $options
     * @param array<string, string> $externalServices gacela key => symfony service id
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
     * Through a closure, so a Symfony service reaches Gacela without being
     * constructed by the act of configuring it: a bridge that instantiated the
     * entity manager on every boot would cost more than it saves.
     */
    private function applyExternalServices(GacelaConfig $config): void
    {
        foreach ($this->externalServices as $key => $serviceId) {
            $config->addExternalService($key, fn (): object => $this->service($serviceId));
        }
    }

    private function service(string $serviceId): object
    {
        /** @var object $service */
        $service = $this->services->get($serviceId);

        return $service;
    }
}
