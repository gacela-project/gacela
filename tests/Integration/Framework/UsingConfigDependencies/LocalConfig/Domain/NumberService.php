<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain;

final class NumberService
{
    private NumberGeneratorInterface $numberGenerator;
    private GreeterGeneratorInterface $greeterGenerator;

    public function __construct(
        NumberGeneratorInterface $numberGenerator,
        GreeterGeneratorInterface $greeterGenerator
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->greeterGenerator = $greeterGenerator;
    }

    public function generateNumberString(): string
    {
        $number = $this->numberGenerator->getNumber();

        return $this->greeterGenerator->greet((string)$number);
    }
}
