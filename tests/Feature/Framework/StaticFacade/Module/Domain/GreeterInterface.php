<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\StaticFacade\Module\Domain;

interface GreeterInterface
{
    public function greet(string $name): string;
}
