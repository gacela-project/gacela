<?php

declare(strict_types=1);

use Gacela\Framework\AbstractConfigGacela;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvedClassInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAbstractAnonClassCallable;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAbstractAnonClassFunction;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAnonClassCallableInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAnonClassFunctionInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\CorrectCompanyGenerator;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\CustomResolvedClass;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Infrastructure\IncorrectCompanyGenerator;

return static function (array $globalServices = []): AbstractConfigGacela {
    return new class($globalServices) extends AbstractConfigGacela {
        public function mappingInterfaces(): array
        {
            $interfaces = [
                ResolvingAnonClassCallableInterface::class => $this->resolvingAnonClassCallableInterface(),
                ResolvingAnonClassFunctionInterface::class => $this->resolvingAnonClassFunctionInterface(),
                ResolvingAbstractAnonClassCallable::class  => $this->resolvingAbstractAnonClassCallable(),
                ResolvingAbstractAnonClassFunction::class  => $this->resolvingAbstractAnonClassFunction(),
                GreeterGeneratorInterface::class => IncorrectCompanyGenerator::class,
                ResolvedClassInterface::class => new CustomResolvedClass(true, 'string', 1, 1.2, ['array']),
                // ResolvedClassInterface::class => CustomResolvedClass::class, // Not yet when non-empty constructor...
            ];

            if ('yes!' === $this->getGlobalService('isWorking?')) {
                $interfaces[GreeterGeneratorInterface::class] = CorrectCompanyGenerator::class;
            }

            return $interfaces;
        }

        private function resolvingAnonClassCallableInterface(): ResolvingAnonClassCallableInterface
        {
            return new class() implements ResolvingAnonClassCallableInterface {
                public function getTypesAnonClassCallable(): array
                {
                    return [true, 'string', 1, 1.2, ['array']];
                }
            };
        }

        private function resolvingAnonClassFunctionInterface(): callable
        {
            return static fn () => new class() implements ResolvingAnonClassFunctionInterface {
                public function getTypesAnonClassFunction(): array
                {
                    return [true, 'string', 1, 1.2, ['array']];
                }
            };
        }

        private function resolvingAbstractAnonClassCallable(): callable
        {
            return static fn () => new class() extends ResolvingAbstractAnonClassCallable {
                public function getTypesAbstractAnonClassCallable(): array
                {
                    return [true, 'string', 1, 1.2, ['array']];
                }
            };
        }

        private function resolvingAbstractAnonClassFunction(): ResolvingAbstractAnonClassFunction
        {
            return new class() extends ResolvingAbstractAnonClassFunction {
                public function getTypesAbstractAnonClassFunction(): array
                {
                    return [true, 'string', 1, 1.2, ['array']];
                }
            };
        }
    };
};
