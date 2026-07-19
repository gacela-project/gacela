<?php

declare(strict_types=1);

namespace GacelaTest\Benchmark;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;

/**
 * Warm facade access through anonymous classes registered via
 * Gacela::addGlobal() (facade -> factory -> config + provider). Complements
 * ModuleExampleBench, which covers the same flow for a conventional on-disk
 * module discovered by naming convention.
 *
 * Sampling: inherits the phpbench.json defaults — see tests/Benchmark/README.md.
 */
#[BeforeMethods('setUp')]
#[Groups(['gate', 'resolve'])]
final class GacelaGlobalBench
{
    private AbstractFacade $facade;

    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__);

        $this->setupCustomConfig();
        $this->setupCustomProvider();
        $this->setupCustomFactory();
        $this->setupCustomFacade();
    }

    public function bench_gacela_global(): void
    {
        $this->facade->getConfigValues();
        $this->facade->getValueFromProvider();
    }

    private function setupCustomFacade(): void
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

    private function setupCustomFactory(): void
    {
        Gacela::addGlobal(
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

    private function setupCustomProvider(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('key', 'value');
                }
            },
        );
    }

    private function setupCustomConfig(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractConfig {
                /**
                 * @return list<mixed>
                 */
                public function getValues(): array
                {
                    return ['1', 2, [3]];
                }
            },
        );
    }
}
