<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\Greeter;

use GacelaTest\Feature\Framework\BindingInterfacesWithInnerDependencies\LocalConfig\Domain\GreeterGeneratorInterface;

final class CorrectCompanyGenerator implements GreeterGeneratorInterface
{
    private CustomNameGenerator $nameGenerator;

    /**
     * @param CustomNameGenerator $nameGenerator This will be automagically resolved.
     *                                           See gacela.php in this Feature test.
     */
    public function __construct(CustomNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    public function company(string $name): string
    {
        $names = $this->nameGenerator->getNames();

        return "Hello {$name}! Name: {$names}";
    }
}
