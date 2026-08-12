<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService\Catalog;

use Gacela\Framework\AbstractFacade;

/**
 * @method CatalogFactory getFactory()
 */
final class CatalogFacade extends AbstractFacade
{
    public function label(): string
    {
        return $this->getFactory()->label();
    }
}
