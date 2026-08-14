<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\CacheableKeyIgnoresArgumentsAnalyser;

/**
 * @see CacheableKeyIgnoresArgumentsAnalyser for what is checked and why
 */
final class CacheableKeyIgnoresArgumentsRule extends InClassMethodAnalyserRule
{
    public function __construct()
    {
        parent::__construct(new CacheableKeyIgnoresArgumentsAnalyser());
    }
}
