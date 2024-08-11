<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

final class CustomClassWithDependencies implements CustomInterface
{
    public function __construct(
        private readonly StringValueInterface $stringValue,
    ) {
    }

    public function getStringValue(): StringValueInterface
    {
        return $this->stringValue;
    }
}
