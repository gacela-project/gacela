<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

use Gacela\Framework\AbstractFacade;

final class InSyncFacade extends AbstractFacade implements InSyncFacadeInterface
{
    public function known(): string
    {
        return 'known';
    }

    public function alsoKnown(): int
    {
        return 1;
    }

    protected function notPublic(): string
    {
        return 'ignored';
    }
}
