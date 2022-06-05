<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\ClassNameCache\ModuleE;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ConfigE getConfig()
 */
final class FactoryE extends AbstractFactory
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
