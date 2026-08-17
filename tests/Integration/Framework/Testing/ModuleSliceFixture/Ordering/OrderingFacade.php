<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Testing\ModuleSliceFixture\Ordering;

use Gacela\Framework\AbstractFacade;

/**
 * The module under test in every slice below.
 *
 * @extends AbstractFacade<OrderingFactory>
 */
final class OrderingFacade extends AbstractFacade
{
    public function quote(string $article): string
    {
        return $this->getFactory()->createQuote()->describe($article);
    }
}
