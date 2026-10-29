<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

use function sprintf;

final class AnonymousClassesTest extends TestCase
{
    protected function setUp(): void
    {
        Gacela::bootstrap(__DIR__);
    }

    public function test_class_resolver_using_anonymous_classes(): void
    {
        $this->registerConfig();
        $this->registerFactory();
        $this->registerAbstractProvider();

        $facade = new class() extends AbstractFacade {
            public function getSomething(): array
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getConfigValues();
            }

            public function greet(string $name): string
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->greet($name);
            }
        };

        self::assertSame([1, 2, 3, 4, 5], $facade->getSomething());
        self::assertSame('Hello, Chema!', $facade->greet('Chema'));
    }

    private function registerConfig(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractConfig {
                /**
                 * @return int[]
                 */
                public function getValues(): array
                {
                    return [1, 2, 3, 4, 5];
                }
            },
            $this,
        );
    }

    private function registerFactory(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var object $myService */
                    $myService = $this->getProvidedDependency('my-greeter');

                    /** @var int[] $configValues */
                    $configValues = $this->getConfig()->getValues();

                    return new class($myService, ...$configValues) {
                        /** @var int[] */
                        private readonly array $configValues;

                        public function __construct(
                            private readonly object $myService,
                            int ...$configValues,
                        ) {
                            $this->configValues = $configValues;
                        }

                        public function getConfigValues(): array
                        {
                            return $this->configValues;
                        }

                        public function greet(string $name): string
                        {
                            return $this->myService->greet($name);
                        }
                    };
                }
            },
            self::class,
        );
    }

    private function registerAbstractProvider(): void
    {
        Gacela::addGlobal(
            new class() extends AbstractProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('my-greeter', new class() {
                        public function greet(string $name): string
                        {
                            return sprintf('Hello, %s!', $name);
                        }
                    });
                }
            },
        );
    }
}
