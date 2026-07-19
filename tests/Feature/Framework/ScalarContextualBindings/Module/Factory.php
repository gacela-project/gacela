<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ScalarContextualBindings\Module;

use Gacela\Framework\AbstractFactory;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly string $greeting = 'default',
    ) {
    }

    public function greeting(): string
    {
        return $this->greeting;
    }
}
