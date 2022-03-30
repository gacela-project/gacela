<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\AnonymousGlobal;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Container\Container;

/**
 * @BeforeMethods("setUp")
 */
final class AnonymousGlobalsBench
{
    private AbstractFacade $facade;

    public function setUp(): void
    {
        $this->abstractConfig();
        $this->abstractDependencyProvider();
        $this->abstractFactory();
        $this->abstractFacade();
    }

    public function bench_class_resolving(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromDependencyProvider();
    }

    private function abstractConfig(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractConfig {
                public function getValues(): array
                {
                    return ['1', 2, [3]];
                }
            }
        );
    }

    private function abstractDependencyProvider(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractDependencyProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('key', 'value');
                }
            }
        );
    }

    private function abstractFactory(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var array $configValues */
                    $configValues = $this->getConfig()->getValues();

                    /** @var string $valueFromDependencyProvider */
                    $valueFromDependencyProvider = $this->getProvidedDependency('key');

                    return new class($configValues, $valueFromDependencyProvider) {
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
    }

    private function abstractFacade(): void
    {
        $this->facade = new class() extends AbstractFacade {
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
}
