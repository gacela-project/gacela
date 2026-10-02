<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Architecture\StatefulFixture\Farewell;

use ArrayObject;
use Gacela\Framework\AbstractFactory;

use function str_repeat;

final class FarewellFactory extends AbstractFactory
{
    public function farewell(string $name): string
    {
        // Through the module container, which a module without a Provider still has.
        return 'Bye ' . $name . str_repeat('!', $this->make(ArrayObject::class)->count());
    }
}
