<?php

declare(strict_types=1);

namespace Gacela\SymfonyBridge;

use Gacela\Framework\Gacela;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Drops what one request left in Gacela when Symfony resets its services
 * between requests: under FrankenPHP worker mode, RoadRunner, or a Messenger
 * worker. The warm caches stay, so the next request does not bootstrap again.
 */
final class GacelaRequestStateResetter implements ResetInterface
{
    public function reset(): void
    {
        Gacela::resetRequestState();
    }
}
