<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\GlobalServices;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use PHPUnit\Framework\TestCase;

final class GlobalServicesTest extends TestCase
{
    private AbstractFacade $facade;

    public function setUp(): void
    {
        AbstractClassResolver::addGlobal(
            '\module-name@anonymous\Config',
            new class() extends AbstractConfig {
                public function getValues(): array
                {
                    return ['1', 2, [3]];
                }
            }
        );

        AbstractClassResolver::addGlobal(
            '\module-name@anonymous\Factory',
            new class() extends AbstractFactory {
                public function createDomainClass(): object
                {
                    return new class($this->getConfig()) {
                        private AbstractConfig $config;

                        public function __construct(AbstractConfig $config)
                        {
                            $this->config = $config;
                        }

                        public function getConfigValues(): array
                        {
                            return $this->config->getValues();
                        }
                    };
                }
            }
        );

        $this->facade = new class() extends AbstractFacade {
            public function getSomething(): array
            {
                return $this->getFactory()
                    ->createDomainClass()
                    ->getConfigValues();
            }
        };
    }

    public function test_factory(): void
    {
        self::assertSame(
            ['1', 2, [3]],
            $this->facade->getSomething()
        );
    }
}
