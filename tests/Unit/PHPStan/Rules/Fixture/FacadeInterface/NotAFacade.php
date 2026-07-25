<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

final class NotAFacade implements InSyncFacadeInterface
{
    public function known(): string
    {
        return 'known';
    }

    public function alsoKnown(): int
    {
        return 1;
    }

    public function extraMethod(): string
    {
        return 'not checked: this does not extend AbstractFacade';
    }
}
