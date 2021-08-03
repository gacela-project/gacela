<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig;

use Gacela\Framework\AbstractFactory;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Domain\NumberService;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomGreeterGenerator;
use GacelaTest\Integration\Framework\UsingConfigDependencies\LocalConfig\Infrastructure\CustomNumberGenerator;

/**
 * @method Config getConfig()
 */
final class Factory extends AbstractFactory
{
    private CustomNumberGenerator $numberGenerator;
    private CustomGreeterGenerator $greeterGenerator;

    public function __construct(
        CustomNumberGenerator $numberGenerator,
        CustomGreeterGenerator $greeterGenerator
    ) {
        $this->numberGenerator = $numberGenerator;
        $this->greeterGenerator = $greeterGenerator;
    }

    public function createNumberService(): NumberService
    {
        return new NumberService(
            $this->numberGenerator,
            $this->greeterGenerator
        );
    }
}
