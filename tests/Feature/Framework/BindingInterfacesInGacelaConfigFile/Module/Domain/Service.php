<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesInGacelaConfigFile\Module\Domain;

final class Service
{
    public function __construct(
        private readonly AbstractClass $resolvedClass,
        private readonly AbstractFromAnonymousClass $resolveAbstractFromAnonymousClass,
        private readonly AbstractFromCallable $resolveAbstractFromCallable,
        private readonly InterfaceFromAnonymousClass $resolveInterfaceFromAnonymousClass,
        private readonly InterfaceFromCallable $resolveInterfaceFromCallable,
    ) {
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
