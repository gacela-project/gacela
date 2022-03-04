<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\AnonymousGlobal;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Container\Container;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * @BeforeMethods("setUp")
 * @Revs(100)
 * @Iterations(10)
 */
final class AnonymousGlobalsBench
{
    private AbstractFacade $facade;

    public function setUp(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class () extends AbstractConfig {
                public function getValues(): array
                {
                    return ['1', 2, [3]];
                }
            }
        );

        AnonymousGlobal::addGlobal(
            $this,
            new class () extends AbstractDependencyProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('key', 'value');
                }
            }
        );

        AnonymousGlobal::addGlobal(
            $this,
            new class () extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var array $configValues */
                    $configValues = $this->getConfig()->getValues();

                    /** @var string $valueFromDependencyProvider */
                    $valueFromDependencyProvider = $this->getProvidedDependency('key');

                    return new class ($configValues, $valueFromDependencyProvider) {
                        private array $configValues;
                        private string $valueFromDependencyProvider;

                        public function __construct(
                            array $configValues,
                            string $valueFromDependencyProvider
                        ) {
                            $this->configValues = $configValues;
                            $this->valueFromDependencyProvider = $valueFromDependencyProvider;
                        }

                        public function getConfigValues(): array
                        {
                            return $this->configValues;
                        }

                        public function getValueFromDependencyProvider(): string
                        {
                            return $this->valueFromDependencyProvider;
                        }
                    };
                }
            }
        );

        $this->facade = new class () extends AbstractFacade {
            public function getConfigValues(): array
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getConfigValues();
            }

            public function getValueFromDependencyProvider(): string
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getValueFromDependencyProvider();
            }
        };
    }

    public function bench_class_resolving(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromDependencyProvider();
    }
}
