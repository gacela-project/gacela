<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Infrastructure;

use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain\AbstractClass;

final class ConcreteClass extends AbstractClass
{
    public function __construct(
        private readonly bool $bool,
        private readonly string $string,
        private readonly int $int,
        private readonly float $float,
        private readonly array $array,
    ) {
    }

    public function getTypes(): array
    {
        return [
            'bool' => $this->bool,
            'string' => $this->string,
            'int' => $this->int,
            'float' => $this->float,
            'array' => $this->array,
        ];
    }
}
