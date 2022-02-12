<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter;

use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use RuntimeException;

final class IncorrectCompanyGenerator implements GreeterGeneratorInterface
{
    public function company(string $name): string
    {
        throw new RuntimeException('Test should fail!');
    }
}
