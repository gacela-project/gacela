<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Customer;

use Gacela\Framework\AbstractConfig;

final class CustomerConfig extends AbstractConfig
{
    /**
     * The tier a customer is registered with unless the caller says otherwise.
     */
    public function defaultTier(): string
    {
        return $this->getString('customer.default_tier');
    }

    /**
     * Seconds a customer lookup may be served from the method cache. Read here
     * rather than written into `#[Cacheable(ttl: ...)]` so an environment can
     * move it; the attribute carries the default the code was written against.
     */
    public function lookupCacheTtl(): int
    {
        return $this->getInt('customer.lookup_cache_ttl', 300);
    }
}
