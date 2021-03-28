<?php

declare(strict_types=1);

namespace GacelaTest\Fixtures\ExampleC\Domain\Service;

use GacelaTest\Fixtures\ExampleA;
use GacelaTest\Fixtures\ExampleB;
use GacelaTest\Fixtures\ExampleC\Infrastructure\Persistence\RepositoryInterface;

final class ServiceC
{
    private int $number;
    private ExampleA\FacadeInterface $exampleAFacade;
    private ExampleB\FacadeInterface $exampleBFacade;
    private RepositoryInterface $repository;

    public function __construct(
        int $number,
        ExampleA\FacadeInterface $exampleAFacade,
        ExampleB\FacadeInterface $exampleBFacade,
        RepositoryInterface $repository
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
