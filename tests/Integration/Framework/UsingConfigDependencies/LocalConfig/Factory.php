<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\GreeterGeneratorInterface;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\NumberService;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    private GreeterGeneratorInterface $companyGenerator;
    private string $randomString;
    private bool $bool;
    private int $integer;
    private float $float;
    /** @var callable */
    private $callable;

    public function __construct(
        GreeterGeneratorInterface $companyGenerator,
        string $randomString,
        bool $bool,
        int $integer,
        float $float,
        callable $callable
    ) {
        $this->companyGenerator = $companyGenerator;
        $this->randomString = $randomString;
        $this->bool = $bool;
        $this->integer = $integer;
        $this->float = $float;
        $this->callable = $callable;
    }

    public function createCompanyService(): NumberService
    {
        return new NumberService($this->companyGenerator);
    }
}
