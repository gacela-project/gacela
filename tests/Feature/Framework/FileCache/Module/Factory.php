<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\FileCache\Module;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Feature\Framework\FileCache\Module\Persistence\FakeRepository;

/**
 * @method FakeRepository getRepository()
 */
final class Factory extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    public function getName(): string
    {
        return $this->getRepository()->findName();
    }
}
