<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\FileCache\ModuleA;

use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ServiceResolverAwareTrait;
use GacelaTest\Benchmark\FileCache\ModuleA\Infrastructure\EntityManager;
use GacelaTest\Benchmark\FileCache\ModuleA\Infrastructure\Repository;
use GacelaTest\Fixtures\StringValueInterface;

/**
 * @method ConfigA getConfig()
 * @method Repository getRepository()
 * @method EntityManager getEntityManager()
 */
final class FactoryA extends AbstractFactory
{
    use ServiceResolverAwareTrait;

    public function __construct(
        private StringValueInterface $stringValue,
    ) {
    }

    public function getArrayConfigAndProvidedDependency(): array
    {
        return [
            'config-key' => $this->getConfig()->getConfigValue(),
            'string-value' => $this->stringValue->value(),
            'provided-dependency' => $this->getProvidedDependency('provided-dependency'),
            'repository' => $this->getRepository()->getAll(),
            'entity-manager' => $this->getEntityManager()->updateEntity(),
        ];
    }
}
