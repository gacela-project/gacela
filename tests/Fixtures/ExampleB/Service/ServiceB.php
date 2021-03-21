<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleB\Service;

use GacelaTest\Fixtures\ExampleA\ExampleAFacade;

final class ServiceB
{
    private ExampleAFacade $exampleAFacade;

    public function __construct(ExampleAFacade $exampleAFacade)
    {
        $this->exampleAFacade = $exampleAFacade;
    }

    public function greet(string $name): array
    {
        return array_merge(
            $this->exampleAFacade->greet($name),
            ["Hello, $name from B."]
        );
    }
}
