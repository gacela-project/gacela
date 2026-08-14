<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingIfPlugin;

final class PackageDefaultClock implements Clock
{
    public function source(): string
    {
        return 'package-default';
    }
}
