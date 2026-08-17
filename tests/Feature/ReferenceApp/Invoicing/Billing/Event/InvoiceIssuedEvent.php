<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Billing\Event;

use Gacela\Framework\Event\GacelaEventInterface;

use function sprintf;

/**
 * An invoice exists. Whoever cares about that is not Billing's business.
 *
 * This is the module's outward announcement, and the reason Billing names no
 * other module in order to make it: it dispatches through the event dispatcher
 * it was given, and `gacela.php` -- the composition root, the one place that is
 * allowed to know both sides -- decides who hears it.
 *
 * In `Billing\Event`, which is one of the sub-namespaces a module publishes by
 * convention, so a subscriber may name this class where it may not name
 * anything under `Billing\Domain`. The direction of that permission is the
 * point: a subscriber knows the event it subscribes to, and the publisher knows
 * nobody.
 *
 * Immutable and self-describing, like every Gacela event: the listener gets
 * facts, not a handle onto Billing.
 */
final class InvoiceIssuedEvent implements GacelaEventInterface
{
    public function __construct(
        private readonly string $invoiceNumber,
        private readonly string $customerName,
        private readonly int $grossCents,
        private readonly string $currency,
    ) {
    }

    public function invoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function customerName(): string
    {
        return $this->customerName;
    }

    public function grossCents(): int
    {
        return $this->grossCents;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * What is owed, as a line a human can read.
     *
     * Written here rather than by the listener: the amount and the currency are
     * Billing's facts, and a subscriber deciding how to phrase them would have
     * to know that `grossCents` is minor units -- which is the kind of thing a
     * module learns about another module and then depends on.
     */
    public function amountDue(): string
    {
        return sprintf('%d %s due', $this->grossCents, $this->currency);
    }

    public function toString(): string
    {
        return sprintf('Invoice %s issued: %s', $this->invoiceNumber, $this->amountDue());
    }
}
