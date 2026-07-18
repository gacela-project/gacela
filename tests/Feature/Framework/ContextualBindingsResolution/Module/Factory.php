<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\ContextualBindingsResolution\Module;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Feature\Framework\ContextualBindingsResolution\Module\Domain\GreeterInterface;

final class Factory extends AbstractFactory
{
    public function __construct(
        private readonly GreeterInterface $greeter,
    ) {
    }

    public function getGreeting(): string
    {
        return $this->greeter->greet();
    }
}
