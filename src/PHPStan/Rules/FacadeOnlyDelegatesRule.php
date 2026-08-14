<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;

/**
 * @see FacadeOnlyDelegatesAnalyser for what is checked and why
 */
final class FacadeOnlyDelegatesRule extends InClassMethodAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new FacadeOnlyDelegatesAnalyser());
    }
}
