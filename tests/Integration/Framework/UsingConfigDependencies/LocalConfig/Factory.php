<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\NumberService;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomCompanyGenerator;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    private CustomCompanyGenerator $companyGenerator;

    public function __construct(CustomCompanyGenerator $companyGenerator)
    {
        $this->companyGenerator = $companyGenerator;
    }

    public function createCompanyService(): NumberService
    {
        return new NumberService($this->companyGenerator);
    }
}
