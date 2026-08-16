<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig;

use Gacela\Framework\ServiceResolver\ServiceMap;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Integration\Framework\ClassResolver\StaleBootstrapConfig\Greeting\GreetingService;

/**
 * A plain class reaching a service through `#[ServiceMap]`, the way a console
 * command or an HTTP controller does.
 *
 * The resolver behind the accessor is held in a trait static, so it survives
 * `Gacela::resetCache()` and the next bootstrap uses the same one -- which is
 * why this class is what the regression beside it is written against.
 */
#[ServiceMap(method: 'getGreetingService', className: GreetingService::class)]
final class Reader
{
    use ServiceResolverAwareTrait;

    public function greet(): string
    {
        /** @var GreetingService $service */
        $service = $this->getGreetingService();

        return $service->greet();
    }
}
