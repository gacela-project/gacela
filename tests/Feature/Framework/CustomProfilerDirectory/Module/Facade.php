<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomProfilerDirectory\Module;

use Gacela\Framework\AbstractFacade;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Feature\Framework\CustomProfilerDirectory\Module\Persistence\FakeRepository;

/**
 * @method FakeRepository getRepository()
 */
final class Facade extends AbstractFacade
{
    use DocBlockResolverAwareTrait;

    public function getName(): string
    {
        return $this->getRepository()->findName();
    }
}
