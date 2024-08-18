<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\Greeter;

use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;

use function sprintf;

final class CorrectCompanyGenerator implements GreeterGeneratorInterface
{
    /**
     * @param CustomNameGenerator $nameGenerator This will be automagically resolved.
     *                                           See gacela.php in this Feature test.
     */
    public function __construct(
        private readonly CustomNameGenerator $nameGenerator,
    ) {
    }

    public function company(string $name): string
    {
        $names = $this->nameGenerator->getNames();

        return sprintf('Hello %s! Name: %s', $name, $names);
    }
}
