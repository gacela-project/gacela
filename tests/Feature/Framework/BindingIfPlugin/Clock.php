<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingIfPlugin;

interface Clock
{
    public function source(): string;
}
