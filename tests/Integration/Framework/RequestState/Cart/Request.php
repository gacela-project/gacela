<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\RequestState\Cart;

/**
 * Stands in for whatever a worker learns per request: the authenticated user.
 */
final class Request
{
    public static string $user = '';
}
