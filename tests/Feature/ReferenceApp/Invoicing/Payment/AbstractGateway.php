<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Payment;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\ConfigResolverAwareTrait;
use GacelaTest\Feature\ReferenceApp\Invoicing\Payment\Domain\PaymentReceipt;

/**
 * The base of the `Gateway` kind this application declares with
 * `addResolvableType()`.
 *
 * A fifth pillar, resolved by the same rules as the four built-in ones: the
 * finder looks for `Payment\PaymentGateway` in this module, the file cache
 * holds the answer, and `doctor` knows the suffix. It exists because "which
 * gateway is this deployment talking to" is a per-module question with exactly
 * one answer, which is what a resolvable kind is for.
 *
 * Deliberately not generic over its Config: a kind is resolved per module, so
 * every subclass would have to restate the same parameter, and the one place
 * that reads a typed config says so locally instead.
 */
abstract class AbstractGateway
{
    /** @use ConfigResolverAwareTrait<AbstractConfig> */
    use ConfigResolverAwareTrait;

    abstract public function capture(string $invoiceNumber, int $amountCents): PaymentReceipt;
}
