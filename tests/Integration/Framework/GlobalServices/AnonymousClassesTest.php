<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractDependencyProvider;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Container\Container;
use PHPUnit\Framework\TestCase;

final class AnonymousClassesTest extends TestCase
{
    public function test_class_resolver_using_anonymous_classes(): void
    {
        $this->registerConfig();
        $this->registerFactory();
        $this->registerDependencyProvider();

        $facade = new class () extends AbstractFacade {
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

    /**
     * Using $this object as context.
     */
    private function registerConfig(): void
    {
        AbstractClassResolver::addAnonymousGlobal(
            $this,
            new class () extends AbstractConfig {
                /**
                 * @return int[]
                 */
                public function getValues(): array
                {
                    return [1, 2, 3, 4, 5];
                }
            }
        );
    }

    /**
     * Using a string (class name) as context.
     */
    private function registerFactory(): void
    {
        AbstractClassResolver::addAnonymousGlobal(
            'AnonymousClassesTest',
            new class () extends AbstractFactory {
                public function createDomainClass(): object
                {
                    /** @var object $myService */
                    $myService = $this->getProvidedDependency('my-greeter');

                    /** @var int[] $configValues */
                    $configValues = $this->getConfig()->getValues();

                    return new class ($myService, ...$configValues) {
                        private object $myService;
                        /** @var int[] */
                        private array $configValues;

                        public function __construct(
                            object $myService,
                            int ...$configValues
                        ) {
                            $this->myService = $myService;
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
            }
        );
    }

    private function registerDependencyProvider(): void
    {
        AbstractClassResolver::addAnonymousGlobal(
            $this,
            new class () extends AbstractDependencyProvider {
                public function provideModuleDependencies(Container $container): void
                {
                    $container->set('my-greeter', new class () {
                        public function greet(string $name): string
                        {
                            return "Hello, $name!";
                        }
                    });
                }
            }
        );
    }
}
