<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingIfPlugin;

final class ApplicationClock implements Clock
{
    public function source(): string
    {
        return 'application';
    }
}
