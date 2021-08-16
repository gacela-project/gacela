<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\BindingInterfacesInGacelaConfigFile\LocalConfig\Domain;

final class Service
{
    private AbstractClass $resolvedClass;
    private AbstractFromAnonymousClass $resolveAbstractFromAnonymousClass;
    private AbstractFromCallable $resolveAbstractFromCallable;
    private InterfaceFromAnonymousClass $resolveInterfaceFromAnonymousClass;
    private InterfaceFromCallable $resolveInterfaceFromCallable;

    public function __construct(
        AbstractClass $resolvedClass,
        AbstractFromAnonymousClass $resolveAbstractFromAnonymousClass,
        AbstractFromCallable $resolvingAbstractAnonClassCallable,
        InterfaceFromAnonymousClass $resolveInterfaceFromAnonymousClass,
        InterfaceFromCallable $resolveInterfaceFromCallable
    ) {
        $this->resolvedClass = $resolvedClass;
        $this->resolveAbstractFromAnonymousClass = $resolveAbstractFromAnonymousClass;
        $this->resolveAbstractFromCallable = $resolvingAbstractAnonClassCallable;
        $this->resolveInterfaceFromAnonymousClass = $resolveInterfaceFromAnonymousClass;
        $this->resolveInterfaceFromCallable = $resolveInterfaceFromCallable;
    }

    public function generateResolvedClass(): array
    {
        return $this->resolvedClass->getTypes();
    }

    public function generateResolveAbstractFromAnonymousClass(): string
    {
        return $this->resolveAbstractFromAnonymousClass->getClassName();
    }

    public function generateResolveAbstractFromCallable(): string
    {
        return $this->resolveAbstractFromCallable->getClassName();
    }

    public function generateResolveInterfaceFromAnonymousClass(): string
    {
        return $this->resolveInterfaceFromAnonymousClass->getClassName();
    }

    public function generateResolveInterfaceFromCallable(): string
    {
        return $this->resolveInterfaceFromCallable->getClassName();
    }
}
