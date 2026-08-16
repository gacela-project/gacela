<?php

declare(strict_types=1);

namespace GacelaTest\Feature\ReferenceApp\Invoicing\Shared\Bootstrap;

use Gacela\Framework\Attribute\CacheableConfig;
use Gacela\Framework\Config\Config;
use GacelaTest\Feature\ReferenceApp\Invoicing\Customer\CustomerFacade;

/**
 * Moves the customer lookup's lifetime out of the attribute and into
 * configuration, so an environment can tighten or loosen it without a deploy of
 * the module.
 *
 * Runs after {@see RegisterCacheableStorage} because plugins run in the order
 * they are declared, and an override is about a store that already exists.
 */
final class ApplyCacheableTtlOverrides
{
    public function __invoke(): void
    {
        CacheableConfig::setTtlOverrides([
            CustomerFacade::class . '::findCustomer' => Config::getInstance()->getInt('customer.lookup_cache_ttl'),
        ]);
    }
}
