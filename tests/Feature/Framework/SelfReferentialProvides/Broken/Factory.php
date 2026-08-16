<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\SelfReferentialProvides\Broken;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function createSound(): string
    {
        /** @var string $sound */
        $sound = $this->getProvidedDependency(Provider::SOUND_ID);

        return $sound;
    }

    public function createSelfReferential(): mixed
    {
        return $this->getProvidedDependency(Provider::SELF_REFERENTIAL_ID);
    }
}
