<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Application;

final class InvalidGreeter
{
    public function greet(string $name): string
    {
        return "Hi, $name!";
    }
}
