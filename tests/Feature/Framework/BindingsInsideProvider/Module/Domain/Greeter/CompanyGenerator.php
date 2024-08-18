<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\Greeter;

use GacelaTest\Feature\Framework\BindingsInsideProvider\Module\Domain\GreeterGeneratorInterface;

use function sprintf;

final class CompanyGenerator implements GreeterGeneratorInterface
{
    public function __construct(
        private readonly Team $team,
    ) {
    }

    public function company(string $name): string
    {
        $teamNames = $this->team->getNames();

        return sprintf('Hello %s! Team: %s', $name, $teamNames);
    }
}
