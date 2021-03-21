<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Service;

use GacelaTest\Fixtures\ExampleA\ExampleAFacade;
use GacelaTest\Fixtures\ExampleB\ExampleBFacade;

final class ServiceC
{
    private ExampleAFacade $exampleAFacade;
    private ExampleBFacade $exampleBFacade;
    private ExampleBFacade $exampleBFacade2;

    public function __construct(
        ExampleAFacade $exampleAFacade,
        ExampleBFacade $exampleBFacade
    ) {
        $this->exampleAFacade = $exampleAFacade;
        $this->exampleBFacade = $exampleBFacade;
//        $this->exampleBFacade2 = $exampleBFacade2;
    }

    public function greet(string $name): array
    {
        return array_merge(
            $this->exampleAFacade->greet($name),
            $this->exampleBFacade->greet($name),
            ["Hello, $name from C."]
        );
    }
}
