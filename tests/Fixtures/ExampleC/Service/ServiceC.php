<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Service;

use GacelaTest\Fixtures\ExampleA\ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\ExampleBFacade;

final class ServiceC
{
    private int $number;
    private ExampleAFacade $exampleAFacade;
    private ExampleBFacade $exampleBFacade;

    public function __construct(
        int $number,
        ExampleAFacade $exampleAFacade,
        ExampleBFacade $exampleBFacade
    ) {
        $this->number = $number;
        $this->exampleAFacade = $exampleAFacade;
        $this->exampleBFacade = $exampleBFacade;
    }

    public function greet(string $name): array
    {
        return array_merge(
            [$this->number],
            $this->exampleAFacade->greet($name),
            $this->exampleBFacade->greet($name),
            ["Hello, $name from C."]
        );
    }
}
