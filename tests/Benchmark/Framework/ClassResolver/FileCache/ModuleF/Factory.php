<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\FileCache\ModuleF;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\DocBlockResolverAwareTrait;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method Config getConfig()
 * @method Repository getRepository()
 */
final class Factory extends AbstractFactory
{
    use DocBlockResolverAwareTrait;

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
            'repository' => $this->getRepository()->getAll(),
        ];
    }
}
