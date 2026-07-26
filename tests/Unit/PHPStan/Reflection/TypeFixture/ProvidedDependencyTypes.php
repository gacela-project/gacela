<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Reflection\TypeFixture;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Unit\PHPStan\Reflection\Fixture\MappedFacade;

use function PHPStan\Testing\assertType;

/**
 * Type assertions for GetProvidedDependencyReturnTypeExtension, checked by
 * GetProvidedDependencyTypeInferenceTest. PSR-4 compliant on purpose: PHPStan
 * has to reflect this class to know `$this` is an AbstractFactory, or the
 * extension never fires and every assertion trivially reads `mixed`.
 */
final class ProvidedDependencyTypes extends AbstractFactory
{
    public function classStringKeyIsTyped(): void
    {
        assertType(MappedFacade::class, $this->getProvidedDependency(MappedFacade::class));
    }

    public function interfaceKeyIsTyped(): void
    {
        assertType(SomeContract::class, $this->getProvidedDependency(SomeContract::class));
    }

    public function stringKeyStaysMixed(): void
    {
        assertType('mixed', $this->getProvidedDependency('some.service'));
    }

    public function unknownClassStringStaysMixed(): void
    {
        assertType('mixed', $this->getProvidedDependency('Not\A\Real\Class'));
    }

    public function nonConstantKeyStaysMixed(string $key): void
    {
        assertType('mixed', $this->getProvidedDependency($key));
    }
}
