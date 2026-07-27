<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules\Fixture\FacadeInterface;

/**
 * Drifted in every way that matters -- it implements the interface named after
 * itself and keeps `extraMethod()` out of it -- except that it is not a facade.
 * Only the AbstractFacade check can keep this quiet.
 */
final class NotAFacade implements NotAFacadeInterface
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
