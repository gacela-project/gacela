<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\NumberService;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvedClassInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAbstractAnonClassCallable;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAbstractAnonClassFunction;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAnonClassCallableInterface;
use GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain\ResolvingAnonClassFunctionInterface;

final class Factory extends AbstractFactory
{
    private GreeterGeneratorInterface $companyGenerator;
    private ResolvedClassInterface $resolvedClass;
    private ResolvingAnonClassFunctionInterface $resolvingAnonClassFunction;
    private ResolvingAnonClassCallableInterface $resolvingAnonClassCallable;
    private ResolvingAbstractAnonClassFunction $resolvingAbstractAnonClassFunction;
    private ResolvingAbstractAnonClassCallable $resolvingAbstractAnonClassCallable;

    public function __construct(
        GreeterGeneratorInterface $companyGenerator,
        ResolvedClassInterface $resolvedClass,
        ResolvingAnonClassFunctionInterface $resolvingAnonClassFunction,
        ResolvingAnonClassCallableInterface $resolvingAnonClassCallable,
        ResolvingAbstractAnonClassFunction $resolvingAbstractAnonClassFunction,
        ResolvingAbstractAnonClassCallable $resolvingAbstractAnonClassCallable
    ) {
        $this->companyGenerator = $companyGenerator;
        $this->resolvedClass = $resolvedClass;
        $this->resolvingAnonClassFunction = $resolvingAnonClassFunction;
        $this->resolvingAnonClassCallable = $resolvingAnonClassCallable;
        $this->resolvingAbstractAnonClassFunction = $resolvingAbstractAnonClassFunction;
        $this->resolvingAbstractAnonClassCallable = $resolvingAbstractAnonClassCallable;
    }

    public function createCompanyService(): NumberService
    {
        return new NumberService(
            $this->companyGenerator,
            $this->resolvedClass,
            $this->resolvingAnonClassFunction,
            $this->resolvingAnonClassCallable,
            $this->resolvingAbstractAnonClassFunction,
            $this->resolvingAbstractAnonClassCallable
        );
    }
}
