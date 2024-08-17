<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark\Framework\ClassResolver\AnonymousGlobal;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;

/**
 * @BeforeMethods("setUp")
 */
final class AnonymousGlobalsBench
{
    private AbstractFacade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);

        $this->setupAbstractConfig();
        $this->setupAbstractProvider();
        $this->setupAbstractFactory();
        $this->setupAbstractFacade();
    }

    public function bench_class_resolving(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromProvider();
    }

    private function setupAbstractConfig(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractConfig {
                public function getValues(): array
                {
                    return ['1', 2, [3]];
                }
            },
        );
    }

    private function setupAbstractProvider(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('key', 'value');
                }
            },
        );
    }

    private function setupAbstractFactory(): void
    {
        AnonymousGlobal::addGlobal(
            $this,
            new class() extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var array $configValues */
                    $configValues = $this->getConfig()->getValues();

                    /** @var string $valueFromProvider */
                    $valueFromProvider = $this->getProvidedDependency('key');

                    return new class($configValues, $valueFromProvider) {
                        public function __construct(
                            private readonly array $configValues,
                            private readonly string $valueFromProvider,
                        ) {
                        }

                        public function getConfigValues(): array
                        {
                            return $this->configValues;
                        }

                        public function getValueFromProvider(): string
                        {
                            return $this->valueFromProvider;
                        }
                    };
                }
            },
        );
    }

    private function setupAbstractFacade(): void
    {
        $this->facade = new class() extends AbstractFacade {
            public function getConfigValues(): array
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getConfigValues();
            }

            public function getValueFromProvider(): string
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getValueFromProvider();
            }
        };
    }
}
