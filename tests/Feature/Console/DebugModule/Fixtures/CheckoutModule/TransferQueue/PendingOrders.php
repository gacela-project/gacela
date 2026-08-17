<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\DebugModule\Fixtures\CheckoutModule\TransferQueue;

/**
 * The near miss. `TransferQueue` merely starts with the published segment
 * `Transfer`, so it stays internal and the section must not list it.
 */
final class PendingOrders
{
}
