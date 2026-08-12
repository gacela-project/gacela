<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ExtendProviderService\Catalog;

use Gacela\Framework\AbstractFactory;

final class CatalogFactory extends AbstractFactory
{
    public function label(): string
    {
        /** @var list<string> $labels */
        $labels = $this->getProvidedDependency(CatalogProvider::LABEL);

        return implode('-', $labels);
    }
}
