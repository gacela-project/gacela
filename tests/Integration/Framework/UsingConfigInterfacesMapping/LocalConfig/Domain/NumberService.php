<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigInterfacesMapping\LocalConfig\Domain;

final class NumberService
{
    private GreeterGeneratorInterface $numberGenerator;
    private ResolvedClassInterface $resolvedClass;
    private ResolvingAnonClassFunctionInterface $resolvingAnonClassFunction;
    private ResolvingAnonClassCallableInterface $resolvingAnonClassCallable;
    private ResolvingAbstractAnonClassFunction $resolvingAbstractAnonClassFunction;
    private ResolvingAbstractAnonClassCallable $resolvingAbstractAnonClassCallable;

    public function __construct(
        GreeterGeneratorInterface $numberGenerator,
        ResolvedClassInterface $resolvedClass,
        ResolvingAnonClassFunctionInterface $resolvingAnonClassFunction,
        ResolvingAnonClassCallableInterface $resolvingAnonClassCallable,
        ResolvingAbstractAnonClassFunction $resolvingAbstractAnonClassFunction,
        ResolvingAbstractAnonClassCallable $resolvingAbstractAnonClassCallable
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->resolvedClass = $resolvedClass;
        $this->resolvingAnonClassFunction = $resolvingAnonClassFunction;
        $this->resolvingAnonClassCallable = $resolvingAnonClassCallable;
        $this->resolvingAbstractAnonClassFunction = $resolvingAbstractAnonClassFunction;
        $this->resolvingAbstractAnonClassCallable = $resolvingAbstractAnonClassCallable;
    }

    public function generateCompanyAndName(): string
    {
        return $this->numberGenerator->company('Gacela');
    }

    public function generateResolvedClass(): array
    {
        return $this->resolvedClass->getTypes();
    }

    public function generateTypesAnonClassFunction(): array
    {
        return $this->resolvingAnonClassFunction->getTypesAnonClassFunction();
    }

    public function generateTypesAnonClassCallable(): array
    {
        return $this->resolvingAnonClassCallable->getTypesAnonClassCallable();
    }

    public function generateTypesAbstractAnonClassFunction(): array
    {
        return $this->resolvingAbstractAnonClassFunction->getTypesAbstractAnonClassFunction();
    }

    public function generateTypesAbstractAnonClassCallable(): array
    {
        return $this->resolvingAbstractAnonClassCallable->getTypesAbstractAnonClassCallable();
    }
}
