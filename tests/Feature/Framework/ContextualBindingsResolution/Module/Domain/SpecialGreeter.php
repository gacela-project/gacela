<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Domain;

final class SpecialGreeter implements GreeterInterface
{
    public function greet(): string
    {
        return 'hello from special';
    }
}
