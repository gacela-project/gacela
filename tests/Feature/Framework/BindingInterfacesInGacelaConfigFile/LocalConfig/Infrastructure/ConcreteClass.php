<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Infrastructure;

use GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain\AbstractClass;

final class ConcreteClass extends AbstractClass
{
    public function __construct(
        private bool $bool,
        private string $string,
        private int $int,
        private float $float,
        private array $array,
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
