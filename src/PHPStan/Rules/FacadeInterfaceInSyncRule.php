<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\FacadeInterfaceInSyncAnalyser;

/**
 * @see FacadeInterfaceInSyncAnalyser for what is checked and why
 */
final class FacadeInterfaceInSyncRule extends InClassAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new FacadeInterfaceInSyncAnalyser());
    }
}
