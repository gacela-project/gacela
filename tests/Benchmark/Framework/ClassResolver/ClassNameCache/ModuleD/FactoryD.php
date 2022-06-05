<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleD;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ConfigD getConfig()
 */
final class FactoryD extends AbstractFactory
{
    private StringValueInterface $stringValue;

    public function __construct(StringValueInterface $stringValue)
    {
        $this->stringValue = $stringValue;
    }

    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'string-value' => $this->stringValue->value(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
        ];
    }
}
