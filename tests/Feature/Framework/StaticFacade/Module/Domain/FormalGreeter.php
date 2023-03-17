<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module\Domain;

final class FormalGreeter implements GreeterInterface
{
    public function greet(string $name): string
    {
        return sprintf('Hello, %s.', $name);
    }
}
