<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\ProjectEvents\Ordering\Domain;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * A project's own event: nothing about it is framework-shaped except the one
 * interface, which is what makes the typed listener registration, the
 * inheritance matching and the `hasListeners()` guard apply to it unchanged.
 */
final class OrderPlacedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $reference,
    ) {
    }

    public function reference(): string
    {
        return $this->reference;
    }

    public function toString(): string
    {
        return sprintf('Order placed: %s', $this->reference);
    }
}
