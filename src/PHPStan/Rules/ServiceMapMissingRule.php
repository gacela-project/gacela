<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\ServiceMapMissingAnalyser;

/**
 * @see ServiceMapMissingAnalyser for what is checked and why
 */
final class ServiceMapMissingRule extends InClassAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new ServiceMapMissingAnalyser());
    }
}
