<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Service;

use GacelaTest\Fixtures\ExampleA\ExampleAFacadeInterface;
use GacelaTest\Fixtures\ExampleB\ExampleBFacadeInterface;
use GacelaTest\Fixtures\ExampleC\ExampleCRepositoryInterface;

final class ServiceC
{
    private int $number;
    private ExampleAFacadeInterface $exampleAFacade;
    private ExampleBFacadeInterface $exampleBFacade;
    private ExampleCRepositoryInterface $repository;

    public function __construct(
        int $number,
        ExampleAFacadeInterface $exampleAFacade,
        ExampleBFacadeInterface $exampleBFacade,
        ExampleCRepositoryInterface $repository
    ) {
        $this->number = $number;
        $this->exampleAFacade = $exampleAFacade;
        $this->exampleBFacade = $exampleBFacade;
        $this->repository = $repository;
    }

    public function greet(string $name): array
    {
        return array_merge(
            [$this->number],
            $this->exampleAFacade->greet($name),
            $this->exampleBFacade->greet($name),
            ["Hello, $name from C."],
            [$this->repository->findExampleQuery()],
        );
    }
}
