<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Service;

use GacelaTest\Fixtures\ExampleA;
use GacelaTest\Fixtures\ExampleB;

final class ServiceC
{
    private int $number;
    private ExampleA\FacadeInterface $exampleAFacade;
    private ExampleB\FacadeInterface $exampleBFacade;

    public function __construct(
        int $number,
        ExampleA\FacadeInterface $exampleAFacade,
        ExampleB\FacadeInterface $exampleBFacade
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
            ["Hello, $name from C."],
        );
    }
}
