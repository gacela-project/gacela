<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module;

use Gacela\Framework\AbstractProvider;
use Gacela\Framework\Container\Container;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\Greeter\CompanyGenerator;
use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;

final class Provider extends AbstractProvider
{
    /** @var array<class-string,class-string> */
    public array $bindings = [
        GreeterGeneratorInterface::class => CompanyGenerator::class,
    ];

    public function provideModuleDependencies(Container $container): void
    {
    }
}
