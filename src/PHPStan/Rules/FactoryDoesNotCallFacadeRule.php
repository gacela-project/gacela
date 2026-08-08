<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\FactoryDoesNotCallFacadeAnalyser;

/**
 * @see FactoryDoesNotCallFacadeAnalyser for what is checked and why
 */
final class FactoryDoesNotCallFacadeRule extends InClassAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new FactoryDoesNotCallFacadeAnalyser());
    }
}
