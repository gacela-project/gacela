<?php

declare(strict_types=1);

namespace Gacela\LaravelBridge;

use Gacela\Framework\Bootstrap\IntegrationBootstrapper;
use Psr\Container\ContainerInterface;

/**
 * Bootstraps Gacela from what the Laravel application already knows.
 *
 * Everything it does is {@see IntegrationBootstrapper}, which is where the logic lives: none of it
 * was specific to Laravel, and both bridges held a byte-identical copy of it.
 * This is the adapter -- it turns Laravel's container into the lookup that
 * takes, and keeps the name and constructor the service provider already constructs.
 *
 * Package tests build fresh applications constantly, so bootstrapping is
 * idempotent by being repeated: each boot re-runs it, and the latest
 * configuration is the one in force.
 */
final class GacelaBootstrapper
{
    private readonly IntegrationBootstrapper $inner;

    /**
     * @param array{cache_dir: ?string, file_cache: ?bool, project_namespaces: list<string>} $options
     * @param array<string, string> $externalServices gacela key => laravel binding id
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
