<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module2\Application;

final class GacelaUseCase
{
    public function getGacelaName(): string
    {
        return 'Gacela';
    }
}
