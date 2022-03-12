<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures;

final class CustomClassWithDependencies implements CustomInterface
{
    private StringValueInterface $stringValue;

    public function __construct(StringValueInterface $stringValue)
    {
        $this->stringValue = $stringValue;
    }

    public function getStringValue(): StringValueInterface
    {
        return $this->stringValue;
    }
}
