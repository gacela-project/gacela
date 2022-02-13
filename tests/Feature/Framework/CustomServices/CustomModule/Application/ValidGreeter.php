<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\CustomServices\CustomModule\Application;

use Gacela\Framework\CustomServiceInterface;

final class ValidGreeter implements CustomServiceInterface
{
    private string $start;

    public function __construct(string $start = 'Hi')
    {
        $this->start = $start;
    }

    public function greet(string $name): string
    {
        return sprintf('%s, %s!', $this->start, $name);
    }
}
