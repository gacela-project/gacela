<?php

declare(strict_types=1);

namespace GacelaTest\Unit\StaticAnalysis\Rules\Fixture\CrossModule\Billing;

/**
 * A subclass of a published class, and not published itself: exporting a base
 * class must not export everything anyone ever extends from it.
 */
final class UnpublishedInvoiceDraft extends PublishedInvoice
{
}
