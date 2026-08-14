<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Framework\Bootstrap\IntegrationBootstrapper;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps Gacela from what the Symfony kernel already knows.
 *
 * Everything it does is {@see IntegrationBootstrapper}, which is where the logic lives: none of it
 * was specific to Symfony, and both bridges held a byte-identical copy of it.
 * This is the adapter -- it turns Symfony's container into the lookup that
 * takes, and keeps the name and constructor the bundle registers as a service and checks with `instanceof`.
 *
 * A kernel can boot more than once in one process (functional tests do it
 * constantly), so bootstrapping is idempotent by being repeated: each boot
 * re-runs it, and the latest configuration is the one in force.
 */
final class GacelaBootstrapper
{
    private readonly IntegrationBootstrapper $inner;

    /**
     * @param array{cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>} $options
     * @param array<string, string> $externalServices gacela key => symfony service id
     */
    public function __construct(
        string $appRootDir,
        array $options,
        ContainerInterface $services,
        array $externalServices = [],
    ) {
        $this->inner = new IntegrationBootstrapper(
            $appRootDir,
            $options,
            static function (string $serviceId) use ($services): object {
                /** @var object $service */
                $service = $services->get($serviceId);

                return $service;
            },
            $externalServices,
        );
    }

    public function bootstrap(): void
    {
        $this->inner->bootstrap();
    }
}
